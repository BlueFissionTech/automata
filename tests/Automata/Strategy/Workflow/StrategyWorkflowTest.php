<?php

namespace BlueFission\Tests\Automata\Strategy\Workflow;

use BlueFission\Str;
use BlueFission\Arr;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Path\Graph;
use BlueFission\Automata\Path\Node;
use BlueFission\Automata\Strategy\CompositeStrategy;
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouteRequest, StrategyRouter, StrategyUsage};
use BlueFission\Automata\Strategy\Workflow\StrategyWorkflow;
use Fiber;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class StrategyWorkflowTest extends TestCase
{
    private function graph(array $configs, array $edges = []): Graph
    {
        $graph = new Graph();
        foreach ($configs as $id => $config) {
            $graph->addNode(new Node($id, null, [], ['workflow' => $config + [
                'strategy_id' => $id, 'strategy_version' => '1',
                'capability_id' => 'infer', 'capability_version' => '1',
            ]]));
        }
        foreach ($edges as [$from, $to, $attributes]) { $graph->connect($from, $to, $attributes); }
        return $graph;
    }

    private function router(array $workers, array &$calls): StrategyRouter
    {
        $adapters = [];
        foreach ($workers as $id => $worker) {
            $adapters[] = new class($id, $worker, $calls) implements IStrategyRouteAdapter {
                public function __construct(private string $id, private $worker, private array &$calls) {}
                public function definition(): StrategyDefinition { return new StrategyDefinition([
                    'id' => $this->id, 'version' => '1', 'capability_id' => 'infer', 'capability_version' => '1',
                    'side_effect_free' => true, 'availability' => 'available',
                ]); }
                public function eligibility(StrategyRouteRequest $request): StrategyEligibility { return new StrategyEligibility(['eligible' => true]); }
                public function estimate(StrategyRouteRequest $request): StrategyUsage { return new StrategyUsage(['invocations' => 1]); }
                public function execute(StrategyRouteRequest $request): StrategyAdapterResult {
                    $this->calls[] = $this->id;
                    $output = ($this->worker)($request->input);
                    return $output instanceof StrategyAdapterResult ? $output : new StrategyAdapterResult(['status' => 'completed', 'code' => 'completed', 'output' => $output]);
                }
            };
        }
        return new StrategyRouter($adapters);
    }

    private function approve(array $request): AutonomyDecision
    {
        return new AutonomyDecision(['allowed' => true, 'subject_id' => $request['subject_id'],
            'capability_id' => $request['capability_id'], 'capability_version' => $request['capability_version']]);
    }

    public function testDetachedPlanRoundTripAndOrdinaryIntelligencePrediction(): void
    {
        $graph = $this->graph(['classify' => [], 'answer' => []], [['classify', 'answer', []]]);
        $plan = new StrategyWorkflow('route', '1', $graph, ['answer']);
        $restored = StrategyWorkflow::fromArray(json_decode(json_encode($plan->toArray(), JSON_THROW_ON_ERROR), true));
        self::assertSame($plan->toArray(), $restored->toArray());
        $graph->connect('answer', 'classify'); // The captured plan cannot change.
        $calls = [];
        $strategy = new CompositeStrategy($restored, $this->router([
            'classify' => fn ($input) => Str::make($input['root'])->upper()->val(),
            'answer' => fn ($input) => $input['predecessors']['classify']['output'] . '!',
        ], $calls), $this->approve(...), 'actor');
        $intelligence = new Intelligence();
        $intelligence->registerStrategy($strategy, 'workflow');
        self::assertSame(['answer' => 'HELLO!'], $intelligence->predict('hello'));
        self::assertSame(['classify', 'answer'], $calls);
        self::assertNull($strategy->lastResult()->toArray()['cost']);
    }

    public function testFibersOverlapAndFanInWaitsForBothTerminalResults(): void
    {
        $plan = new StrategyWorkflow('parallel', '1', $this->graph(['a' => [], 'b' => [], 'join' => []], [
            ['a', 'join', []], ['b', 'join', []],
        ]), ['join']);
        $calls = [];
        $strategy = new CompositeStrategy($plan, $this->router([
            'a' => static function () { Fiber::suspend('a waiting'); return 2; },
            'b' => static function () { Fiber::suspend('b waiting'); return 3; },
            'join' => fn ($input) => $input['predecessors']['a']['output'] + $input['predecessors']['b']['output'],
        ], $calls), $this->approve(...), 'actor');
        $run = $strategy->start('input', 'parallel-run');
        self::assertFalse($run->result()->toArray()['settled']);
        $a = new Fiber(fn () => $run->execute('a'));
        $b = new Fiber(fn () => $run->execute('b'));
        self::assertSame('a waiting', $a->start());
        self::assertSame('b waiting', $b->start());
        self::assertSame([], $run->ready());
        $b->resume();
        self::assertSame([], $run->ready());
        $a->resume();
        self::assertSame(['join'], $run->ready());
        $run->execute('join');
        self::assertSame(['join' => 5], $run->result()->outputs());
        self::assertTrue($run->result()->toArray()['settled']);
    }

    public function testConditionalFallbackAndBoundedRetry(): void
    {
        $plan = new StrategyWorkflow('fallback', '1', $this->graph([
            'primary' => ['maximum_attempts' => 2, 'retry_codes' => ['miss']], 'fallback' => [], 'wrong' => [],
        ], [['primary', 'fallback', ['on' => 'failed']], ['fallback', 'wrong', ['when' => ['path' => [], 'equals' => 'wrong']]]]), ['fallback']);
        $calls = [];
        $strategy = new CompositeStrategy($plan, $this->router([
            'primary' => fn () => new StrategyAdapterResult(['status' => 'failed', 'code' => 'miss']),
            'fallback' => fn () => 'found', 'wrong' => fn () => 'wrong',
        ], $calls), $this->approve(...), 'actor');
        self::assertSame(['fallback' => 'found'], $strategy->predict(null));
        self::assertSame(['primary', 'primary', 'fallback'], $calls);
        self::assertCount(2, $strategy->lastResult()->toArray()['nodes']['primary']['attempts']);
    }

    public function testDenialNeverTriggersFailureFallback(): void
    {
        $plan = new StrategyWorkflow('denied', '1', $this->graph(['a' => [], 'b' => []], [['a', 'b', ['on' => 'failed']]]), ['b']);
        $calls = [];
        $strategy = new CompositeStrategy($plan, $this->router(['a' => fn () => 'a', 'b' => fn () => 'b'], $calls),
            fn () => new AutonomyDecision(), 'actor');
        $run = $strategy->start(null);
        $run->execute('a');
        self::assertSame('exhausted', $run->result()->status());
        self::assertSame([], $calls);
    }

    public function testRaceFreezesWinnerAndKeepsLateWorkerReceipt(): void
    {
        $plan = new StrategyWorkflow('race', '1', $this->graph(['slow' => [], 'fast' => [], 'unused' => []]), ['slow', 'fast'], 1);
        $calls = [];
        $strategy = new CompositeStrategy($plan, $this->router([
            'slow' => static function () { Fiber::suspend(); return 'late'; },
            'fast' => fn () => 'winner', 'unused' => fn () => 'unused',
        ], $calls), $this->approve(...), 'actor');
        $run = $strategy->start(null);
        $slow = new Fiber(fn () => $run->execute('slow'));
        $slow->start();
        $run->execute('fast');
        self::assertSame('completed', $run->result()->status());
        self::assertFalse($run->result()->toArray()['settled']);
        self::assertSame([], $run->ready());
        $slow->resume();
        self::assertSame(['fast' => 'winner'], $run->result()->outputs());
        self::assertSame('late', $run->result()->toArray()['nodes']['slow']['output']);
        self::assertSame(['slow', 'fast'], $calls);
    }

    public function testCancellationDuringAuthorizationDoesNotInvokeAdapter(): void
    {
        $calls = [];
        $plan = new StrategyWorkflow('cancel', '1', $this->graph(['a' => []]), ['a']);
        $strategy = new CompositeStrategy($plan, $this->router(['a' => fn () => 'effect'], $calls),
            function ($request) { Fiber::suspend(); return $this->approve($request); }, 'actor');
        $run = $strategy->start(null);
        $worker = new Fiber(fn () => $run->execute('a'));
        $worker->start();
        $run->cancel();
        $worker->resume();
        self::assertSame('cancelled', $run->result()->status());
        self::assertTrue($run->result()->toArray()['settled']);
        self::assertSame([], $calls);
    }

    public function testUnknownExecutionStopsFallbackAndRetries(): void
    {
        $calls = [];
        $plan = new StrategyWorkflow('uncertain', '1', $this->graph([
            'a' => ['maximum_attempts' => 3, 'retry_codes' => ['execution_exception']], 'b' => [],
        ], [['a', 'b', ['on' => 'failed']]]), ['b']);
        $strategy = new CompositeStrategy($plan, $this->router([
            'a' => fn () => throw new LogicException('private message'), 'b' => fn () => 'b',
        ], $calls), $this->approve(...), 'actor');
        $run = $strategy->start(null);
        $run->execute('a');
        self::assertSame('uncertain', $run->result()->status());
        self::assertSame([], $run->ready());
        self::assertSame(['a'], $calls);
        self::assertStringNotContainsString('private message', json_encode($run->result()->toArray()));
    }

    public function testCyclesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StrategyWorkflow('cycle', '1', $this->graph(['a' => [], 'b' => []], [['a', 'b', []], ['b', 'a', []]]), ['b']);
    }

    public function testOutputReferencesCannotMutateTheCapturedPlan(): void
    {
        $id = 'a';
        $outputs = [&$id];
        $plan = new StrategyWorkflow('immutable', '1', $this->graph(['a' => [], 'b' => []]), $outputs);
        $id = 'b';
        self::assertSame(['a'], $plan->toArray()['outputs']);
    }

    public function testMalformedPlansFailWithoutInvokingAnything(): void
    {
        $cases = [
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => []]), ['a'], 0),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => []]), ['a'], 2),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => []]), ['a', 'a']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => []]), ['unknown']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => ['allowed_modes' => ['unknown']]]), ['a']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => ['allowed_modes' => []]]), ['a']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => ['maximum_attempts' => '2']]), ['a']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => ['maximum_attempts' => 9]]), ['a']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => ['limits' => ['max_cost' => NAN]]]), ['a']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => ['limits' => ['unknown' => 1]]]), ['a']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => ['join' => 'race']]), ['a']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => ['authority' => true]]), ['a']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => [], 'b' => []], [['a', 'b', ['on' => 'denied']]]), ['b']),
            fn () => new StrategyWorkflow('x', '1', $this->graph(['a' => [], 'b' => []], [['a', 'b', ['when' => ['path' => []]]]]), ['b']),
        ];
        foreach ($cases as $index => $case) {
            try { $case(); self::fail('Malformed plan accepted: ' . $index); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function testUnknownImportFieldsDuplicateNodesAndEdgesAreRejected(): void
    {
        $record = (new StrategyWorkflow('x', '1', $this->graph(['a' => [], 'b' => []], [['a', 'b', []]]), ['b']))->toArray();
        $cases = [];
        $cases[] = [...$record, 'authorization' => ['allowed' => true]];
        $cases[] = [...$record, 'schema_version' => 2];
        $cases[] = [...$record, 'nodes' => [...$record['nodes'], $record['nodes'][0]]];
        $cases[] = [...$record, 'edges' => [...$record['edges'], $record['edges'][0]]];
        $cases[] = [...$record, 'edges' => [['from' => 'a', 'to' => 'unknown', 'on' => 'completed']]];
        foreach ($cases as $case) {
            try { StrategyWorkflow::fromArray($case); self::fail('Invalid imported plan accepted.'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function testConditionalEdgesCompareStrictlyAndAnyJoinSelectsMatchingParents(): void
    {
        $plan = new StrategyWorkflow('conditions', '1', $this->graph(['a' => [], 'yes' => [], 'no' => [], 'join' => ['join' => 'any']], [
            ['a', 'yes', ['when' => ['path' => ['value'], 'equals' => 0]]],
            ['a', 'no', ['when' => ['path' => ['value'], 'equals' => '0']]],
            ['yes', 'join', []], ['no', 'join', []],
        ]), ['join']);
        $calls = [];
        $strategy = new CompositeStrategy($plan, $this->router([
            'a' => fn () => ['value' => 0], 'yes' => fn () => 'matched', 'no' => fn () => 'wrong',
            'join' => fn ($input) => Arr::make($input['predecessors'])->keys()->val(),
        ], $calls), $this->approve(...), 'actor');
        self::assertSame(['join' => ['yes']], $strategy->predict(null));
        self::assertSame(['a', 'yes', 'join'], $calls);
    }

    public function testDispatchBudgetCountsRetriesAndAuthorizationIsFreshEachTime(): void
    {
        $plan = new StrategyWorkflow('retry', '1', $this->graph(['a' => ['maximum_attempts' => 8, 'retry_codes' => ['miss']]]), ['a']);
        $calls = [];
        $reviews = [];
        $strategy = new CompositeStrategy($plan, $this->router([
            'a' => fn () => new StrategyAdapterResult(['status' => 'failed', 'code' => 'miss']),
        ], $calls), function ($request) use (&$reviews) { $reviews[] = $request['id']; return $this->approve($request); }, 'actor', 2);
        $run = $strategy->start(null, 'bounded');
        $run->execute('a')->execute('a');
        self::assertSame('exhausted', $run->result()->status());
        self::assertSame(['bounded:a:1', 'bounded:a:2'], $reviews);
        self::assertSame(['a', 'a'], $calls);
        self::assertSame([], $run->ready());
    }

    public function testRevocationOnRetryStopsBeforeSecondInvocation(): void
    {
        $plan = new StrategyWorkflow('retry', '1', $this->graph(['a' => ['maximum_attempts' => 2, 'retry_codes' => ['miss']]]), ['a']);
        $calls = [];
        $reviews = 0;
        $strategy = new CompositeStrategy($plan, $this->router([
            'a' => fn () => new StrategyAdapterResult(['status' => 'failed', 'code' => 'miss']),
        ], $calls), function ($request) use (&$reviews) { return ++$reviews === 1 ? $this->approve($request) : new AutonomyDecision(); }, 'actor');
        $run = $strategy->start(null);
        $run->execute('a')->execute('a');
        self::assertSame('denied', $run->result()->toArray()['nodes']['a']['status']);
        self::assertSame(['a'], $calls);
    }

    public function testRouterRejectsVersionScopeAndInvocationBudgetMismatch(): void
    {
        foreach ([['strategy_version' => 'missing'], ['limits' => ['max_invocations' => 0]], []] as $config) {
            $calls = [];
            $plan = new StrategyWorkflow('routing', '1', $this->graph(['a' => $config]), ['a']);
            $strategy = new CompositeStrategy($plan, $this->router(['a' => fn () => 'wrong'], $calls),
                fn ($request) => $config === [] ? new AutonomyDecision(['allowed' => true, 'subject_id' => 'different']) : $this->approve($request), 'actor');
            $run = $strategy->start(null);
            $run->execute('a');
            self::assertSame('exhausted', $run->result()->status());
            self::assertSame([], $calls);
        }
    }

    public function testDuplicateDispatchIsRejectedWhileWorkerIsRunningAndAfterCompletion(): void
    {
        $calls = [];
        $plan = new StrategyWorkflow('once', '1', $this->graph(['a' => []]), ['a']);
        $strategy = new CompositeStrategy($plan, $this->router(['a' => static function () { Fiber::suspend(); return 'done'; }], $calls), $this->approve(...), 'actor');
        $run = $strategy->start(null);
        $worker = new Fiber(fn () => $run->execute('a'));
        $worker->start();
        foreach ([false, true] as $complete) {
            if ($complete) { $worker->resume(); }
            try { $run->execute('a'); self::fail('Duplicate dispatch accepted.'); }
            catch (LogicException) { self::assertCount(1, $calls); }
        }
    }

    public function testMalformedOutputClosesUncertainBeforeDependentExecution(): void
    {
        $calls = [];
        $plan = new StrategyWorkflow('output', '1', $this->graph(['a' => [], 'b' => []], [['a', 'b', []]]), ['b']);
        $strategy = new CompositeStrategy($plan, $this->router(['a' => fn () => new \stdClass(), 'b' => fn () => 'wrong'], $calls), $this->approve(...), 'actor');
        $run = $strategy->start(null);
        $run->execute('a');
        self::assertSame('uncertain', $run->result()->status());
        self::assertSame(['a'], $calls);
    }

    public function testLateExceptionPreservesWinnerButReportsUncertainty(): void
    {
        $calls = [];
        $plan = new StrategyWorkflow('late', '1', $this->graph(['slow' => [], 'fast' => []]), ['slow', 'fast'], 1);
        $strategy = new CompositeStrategy($plan, $this->router([
            'slow' => static function () { Fiber::suspend(); throw new LogicException('late'); }, 'fast' => fn () => 'winner',
        ], $calls), $this->approve(...), 'actor');
        $run = $strategy->start(null);
        $slow = new Fiber(fn () => $run->execute('slow'));
        $slow->start();
        $run->execute('fast');
        $before = $run->result();
        $slow->resume();
        self::assertSame('completed', $before->status());
        self::assertSame('uncertain', $run->result()->status());
        self::assertSame(['fast' => 'winner'], $run->result()->outputs());
        self::assertTrue($run->result()->toArray()['settled']);
    }

    public function testThresholdTwoRequiresTwoSuccessfulOutputs(): void
    {
        $calls = [];
        $plan = new StrategyWorkflow('threshold', '1', $this->graph(['a' => [], 'b' => [], 'c' => []]), ['a', 'b', 'c'], 2);
        $strategy = new CompositeStrategy($plan, $this->router(['a' => fn () => 'A', 'b' => fn () => 'B', 'c' => fn () => 'C'], $calls), $this->approve(...), 'actor');
        $run = $strategy->start(null);
        $run->execute('a');
        self::assertSame('running', $run->result()->status());
        $run->execute('b');
        self::assertSame(['a' => 'A', 'b' => 'B'], $run->result()->outputs());
        self::assertSame(['a', 'b'], $calls);
    }

    public function testUnsupportedTrainingAndModelPersistenceNeverPretendToSucceed(): void
    {
        $calls = [];
        $plan = new StrategyWorkflow('declared', '1', $this->graph(['a' => []]), ['a']);
        $strategy = new CompositeStrategy($plan, $this->router([], $calls), $this->approve(...), 'actor');
        foreach ([fn () => $strategy->train([], []), fn () => $strategy->accuracy(), fn () => $strategy->saveModel('unused'), fn () => $strategy->loadModel('unused')] as $call) {
            try { $call(); self::fail('Unsupported operation accepted.'); }
            catch (LogicException) { self::assertTrue(true); }
        }
    }

    public function testFalseZeroAndEmptyOutputsRetainExactTypes(): void
    {
        $plan = new StrategyWorkflow('values', '1', $this->graph(['a' => []]), ['a']);
        foreach ([false, 0, 0.0, '', [], null] as $value) {
            $calls = [];
            $strategy = new CompositeStrategy($plan, $this->router(['a' => fn () => $value], $calls), $this->approve(...), 'actor');
            self::assertSame(['a' => $value], $strategy->predict(null));
        }
    }

    public function testAnyJoinCanStartWhileAnUnneededParentIsStillRunning(): void
    {
        $calls = [];
        $plan = new StrategyWorkflow('any', '1', $this->graph(['a' => [], 'b' => [], 'join' => ['join' => 'any']], [
            ['a', 'join', []], ['b', 'join', []],
        ]), ['join']);
        $strategy = new CompositeStrategy($plan, $this->router([
            'a' => static function () { Fiber::suspend(); return 'late'; }, 'b' => fn () => 'first',
            'join' => fn ($input) => Arr::make($input['predecessors'])->keys()->val(),
        ], $calls), $this->approve(...), 'actor');
        $run = $strategy->start(null);
        $slow = new Fiber(fn () => $run->execute('a'));
        $slow->start();
        $run->execute('b');
        self::assertSame(['join'], $run->ready());
        $run->execute('join');
        self::assertSame(['join' => ['b']], $run->result()->outputs());
        $slow->resume();
        self::assertSame(['join' => ['b']], $run->result()->outputs());
    }

    public function testNestedCompositeUsesTheSamePredictionInterface(): void
    {
        $innerCalls = $outerCalls = [];
        $inner = new CompositeStrategy(new StrategyWorkflow('inner', '1', $this->graph(['leaf' => []]), ['leaf']),
            $this->router(['leaf' => fn ($input) => Str::make($input['root'])->upper()->val()], $innerCalls), $this->approve(...), 'actor');
        $outer = new CompositeStrategy(new StrategyWorkflow('outer', '1', $this->graph(['nested' => []]), ['nested']),
            $this->router(['nested' => fn ($input) => $inner->predict($input['root'])], $outerCalls), $this->approve(...), 'actor');
        self::assertSame(['nested' => ['leaf' => 'HELLO']], $outer->predict('hello'));
        self::assertSame(['leaf'], $innerCalls);
        self::assertSame(['nested'], $outerCalls);
    }

    public function testAuthorizationFailuresRemainTerminalAndNeverInvokeAdapters(): void
    {
        foreach ([fn () => true, fn () => throw new LogicException('private policy')] as $authorize) {
            $calls = [];
            $strategy = new CompositeStrategy(new StrategyWorkflow('auth', '1', $this->graph(['a' => []]), ['a']),
                $this->router(['a' => fn () => 'wrong'], $calls), $authorize, 'actor');
            $run = $strategy->start(null);
            $run->execute('a');
            self::assertSame('denied', $run->result()->toArray()['nodes']['a']['status']);
            self::assertSame([], $calls);
            self::assertStringNotContainsString('private policy', json_encode($run->result()));
        }
    }

    public function testRunInputsAreDetachedAndInvalidBeforeAnyDispatch(): void
    {
        $calls = [];
        $strategy = new CompositeStrategy(new StrategyWorkflow('input', '1', $this->graph(['a' => []]), ['a']),
            $this->router(['a' => fn ($input) => $input['root']], $calls), $this->approve(...), 'actor');
        $value = 'original';
        $run = $strategy->start(['value' => &$value]);
        $value = 'mutated';
        $run->execute('a');
        self::assertSame(['a' => ['value' => 'original']], $run->result()->outputs());
        $this->expectException(InvalidArgumentException::class);
        $strategy->start(['unsafe' => new \stdClass()]);
    }

    public function testDeepOutputsBecomeUncertainWhileTheResultRemainsReadable(): void
    {
        $calls = [];
        $value = 'leaf';
        for ($depth = 0; $depth < 28; ++$depth) { $value = ['next' => $value]; }
        $strategy = new CompositeStrategy(new StrategyWorkflow('deep', '1', $this->graph(['a' => []]), ['a']),
            $this->router(['a' => fn () => $value], $calls), $this->approve(...), 'actor');
        $run = $strategy->start(null);
        $run->execute('a');
        self::assertSame('uncertain', $run->result()->status());
        self::assertNull($run->result()->toArray()['nodes']['a']['output']);
        self::assertTrue($run->result()->toArray()['settled']);
    }

    public function testAuthorizationEvidenceIsDetachedFromTheHostDecision(): void
    {
        $calls = [];
        $decision = new AutonomyDecision(['allowed' => true, 'subject_id' => 'actor',
            'capability_id' => 'infer', 'capability_version' => '1', 'packet_id' => 'reviewed-packet']);
        $strategy = new CompositeStrategy(new StrategyWorkflow('evidence', '1', $this->graph(['a' => []]), ['a']),
            $this->router(['a' => static function () { Fiber::suspend(); return 'done'; }], $calls), fn () => $decision, 'actor');
        $run = $strategy->start(null);
        $worker = new Fiber(fn () => $run->execute('a'));
        $worker->start();
        $decision->packet_id = 'changed';
        $worker->resume();
        $receipt = $run->result()->toArray()['nodes']['a']['attempts'][0];
        self::assertSame('reviewed-packet', $receipt['authorization']['packet_id']);
        self::assertSame('actor', $receipt['authorization']['subject_id']);
        self::assertSame(64, Str::make($receipt['input_fingerprint'])->len());
    }
}
