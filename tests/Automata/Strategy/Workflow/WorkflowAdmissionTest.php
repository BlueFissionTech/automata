<?php

namespace BlueFission\Tests\Automata\Strategy\Workflow;

use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Path\{Graph, Node};
use BlueFission\Automata\Strategy\CompositeStrategy;
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, IStrategyDispatchGuard, StrategyDispatchContext, StrategyDispatchDecision, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouteRequest, StrategyRouter, StrategyUsage};
use BlueFission\Automata\Strategy\Workflow\WorkflowAdmissionPolicy;
use BlueFission\Automata\Support\RecordSnapshot;
use BlueFission\Arr;
use Fiber;
use PHPUnit\Framework\TestCase;

/** Provider-free scheduler probes: suspension, never wall time, controls interleaving. */
final class WorkflowAdmissionTest extends TestCase
{
    private function fixture(int $limit = 2, ?callable $authorize = null, ?callable $clock = null, ?int $deadline = null, array $configs = []): array
    {
        $graph = new Graph();
        $adapters = [];
        foreach (['a', 'b', 'c', 'join'] as $id) {
            $graph->addNode(new Node($id, null, [], ['workflow' => [
                'strategy_id' => $id, 'strategy_version' => '1', 'capability_id' => 'read', 'capability_version' => '1',
                ...($configs[$id] ?? []),
            ]]));
            $adapters[$id] = new AdmissionProbe($id);
        }
        foreach (['a', 'b', 'c'] as $id) { $graph->connect($id, 'join', []); }
        $router = new AdmissionRouter(Arr::make($adapters)->values()->val());
        $strategy = new CompositeStrategy(new \BlueFission\Automata\Strategy\Workflow\StrategyWorkflow('reads', '1', $graph, ['join']),
            $router, $authorize ?? self::approve(...), 'actor');
        return [$strategy->startAdmitted(['input' => 1], new WorkflowAdmissionPolicy($limit), $clock ?? static fn () => 0,
            $deadline, 'run', 'context'), $adapters, $router, $strategy];
    }

    public static function approve(array $request): AutonomyDecision
    {
        return new AutonomyDecision(['allowed' => true, 'subject_id' => $request['subject_id'],
            'capability_id' => $request['capability_id'], 'capability_version' => $request['capability_version']]);
    }

    public function testCapacityCountsSuspendedAuthorizationAndDirectCallsCannotBypassIt(): void
    {
        foreach ([1, 2] as $limit) {
            $authorizations = 0;
            [$run, $adapters] = $this->fixture($limit, static function ($request) use (&$authorizations) {
                ++$authorizations;
                Fiber::suspend('authorizing');
                return self::approve($request);
            });
            $fibers = [];
            foreach (Arr::make(['a', 'b', 'c'])->slice(0, $limit)->val() as $id) {
                $fibers[$id] = new Fiber(fn () => $run->execute($id));
                self::assertSame('authorizing', $fibers[$id]->start());
            }
            $before = $run->result()->toArray();
            $run->execute('c'); // An expected capacity refusal, not a failed attempt.
            $after = $run->result()->toArray();
            self::assertSame($limit, $authorizations);
            self::assertSame('pending', $after['nodes']['c']['status']);
            self::assertSame([], $after['nodes']['c']['attempts']);
            self::assertSame($before['dispatches'], $after['dispatches']);
            self::assertSame(0, $after['admission']['adapter_invocations']);
            self::assertSame($limit, $after['admission']['in_flight']);
            self::assertSame('capacity_unavailable', $after['admission']['last_outcome']['code']);
            foreach ($fibers as $fiber) { $fiber->resume(); }
            self::assertSame(0, $run->result()->toArray()['admission']['in_flight']);
            self::assertSame($limit, $run->result()->toArray()['admission']['adapter_invocations']);
            self::assertSame(0, $adapters['c']->calls);
        }
    }

    public function testEverySuspendingPreflightIsFollowedByCancelAndDeadlineGuard(): void
    {
        foreach (['authorize', 'advice', 'eligibility', 'estimate'] as $phase) {
            foreach (['cancel', 'expire'] as $stop) {
                $now = 0;
                $auth = static function ($request) use ($phase) {
                    if ($phase === 'authorize') { Fiber::suspend('authorize'); }
                    return self::approve($request);
                };
                [$run, $adapters, $router] = $this->fixture(2, $auth, static function () use (&$now) { return $now; }, 10);
                $adapters['a']->suspend = $phase;
                $router->suspendAdvice = $phase === 'advice';
                $fiber = new Fiber(fn () => $run->execute('a'));
                self::assertSame($phase, $fiber->start());
                if ($stop === 'cancel') { $run->cancel(); } else { $now = 10; }
                $fiber->resume();
                $record = $run->result()->toArray();
                self::assertSame(0, $adapters['a']->calls, "$phase/$stop");
                self::assertSame(0, $record['admission']['adapter_invocations']);
                self::assertSame(0, $record['admission']['execution_attempts']);
                self::assertSame(0, $record['admission']['in_flight']);
                self::assertSame($stop === 'cancel' ? 'cancelled' : 'expired', $record['status']);
                self::assertFalse($record['nodes']['a']['attempts'][0]['executed']);
                self::assertSame('skipped', $record['nodes']['join']['status']);
            }
        }
    }

    public function testReturnedUncertaintyReleasesItsSlotButSuspendedInvocationDoesNot(): void
    {
        [$run, $adapters] = $this->fixture();
        $adapters['a']->suspend = 'execute';
        $a = new Fiber(fn () => $run->execute('a'));
        $a->start();
        $adapters['b']->work = static fn () => throw new \RuntimeException('unknown');
        $run->execute('b');
        $snapshot = $run->result()->toArray();
        self::assertSame('uncertain', $snapshot['status']);
        self::assertFalse($snapshot['settled']);
        self::assertSame(1, $snapshot['admission']['in_flight']);
        self::assertSame(2, $snapshot['admission']['adapter_invocations']);
        $b = $snapshot['nodes']['b']['attempts'];
        $a->resume();
        $after = $run->result()->toArray();
        self::assertTrue($after['settled']);
        self::assertSame(0, $after['admission']['in_flight']);
        self::assertSame($b, $after['nodes']['b']['attempts']);
        self::assertTrue($after['nodes']['a']['attempts'][0]['admission']['late']);
        self::assertSame(0, $adapters['c']->calls);
    }

    public function testJoinUsesDetachedDeclaredPredecessorsNotCompletionOrder(): void
    {
        [$run, $adapters] = $this->fixture(2);
        $adapters['a']->suspend = $adapters['b']->suspend = 'execute';
        $a = new Fiber(fn () => $run->execute('a'));
        $b = new Fiber(fn () => $run->execute('b'));
        $a->start(); $b->start(); $b->resume();
        $run->execute('c');
        self::assertSame([], $run->ready());
        $a->resume();
        self::assertSame(['join'], $run->ready());
        $run->execute('join');
        $record = $run->result()->toArray();
        self::assertSame(['a', 'b', 'c'], Arr::make($record['outputs']['join']['predecessors'])->keys()->val());
        $record['nodes']['a']['attempts'][0]['admission']['reservation_token'] = 'changed';
        self::assertNotSame('changed', $run->result()->toArray()['nodes']['a']['attempts'][0]['admission']['reservation_token']);
        self::assertTrue($run->result()->toArray()['settled']);
    }

    public function testClockAndPolicyFailClosedWithoutDispatch(): void
    {
        foreach ([0, -1, 1.5, '2', true] as $invalid) {
            try { new WorkflowAdmissionPolicy($invalid); self::fail('Invalid limit accepted'); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
        $policy = new WorkflowAdmissionPolicy(2);
        self::assertSame(['schema_version' => 1, 'version' => '1', 'maximum_in_flight' => 2], $policy->toArray());
        foreach ([static fn () => Fiber::suspend('clock'), static fn () => -1, static fn () => '0'] as $clock) {
            [$run, $adapters] = $this->fixture(clock: $clock);
            self::assertSame([], $run->ready());
            self::assertSame('denied', $run->result()->status());
            self::assertSame(0, $adapters['a']->calls);
        }
        [$run] = $this->fixture(clock: static fn () => 10, deadline: 10);
        self::assertSame([], $run->ready());
        self::assertSame('expired', $run->result()->status());
    }

    public function testGuardFailureSuspensionAndFallbackAreNotInvokedOrRetried(): void
    {
        foreach (['deny', 'throw', 'suspend', 'invalid', 'second'] as $mode) {
            $a = new AdmissionProbe('a'); $b = new AdmissionProbe('b');
            $a->work = static fn () => new StrategyAdapterResult(['status' => 'failed', 'code' => 'miss']);
            $router = new StrategyRouter([$a, $b]);
            $request = new StrategyRouteRequest(['id' => 'r', 'subject_id' => 'actor', 'capability_id' => 'read', 'capability_version' => '1',
                'candidates' => [['id' => 'a', 'version' => '1'], ['id' => 'b', 'version' => '1']], 'escalation_policy' => 'next_eligible']);
            $guard = new class($mode) implements IStrategyDispatchGuard {
                public int $checks = 0;
                public function __construct(private string $mode) {}
                public function check(StrategyDispatchContext $context): StrategyDispatchDecision {
                    ++$this->checks;
                    if ($this->mode === 'throw') { throw new \RuntimeException('guard'); }
                    if ($this->mode === 'suspend') { Fiber::suspend('forbidden'); }
                    if ($this->mode === 'invalid') { return null; }
                    return new StrategyDispatchDecision($this->mode === 'second' && $this->checks === 1, 'dispatch_guard_denied');
                }
            };
            $result = $router->routeGuarded($request, self::approve($request->toArray()), $guard);
            self::assertSame('denied', $result->status);
            self::assertSame($mode === 'second' ? 1 : 0, $a->calls);
            self::assertSame(0, $b->calls);
            self::assertFalse($result->attempts[array_key_last($result->attempts)]['executed']);
        }
    }

    public function testLegacySubclassAndNamedCallsAreUnchanged(): void
    {
        $router = new class extends StrategyRouter {
            public function route(StrategyRouteRequest $request, AutonomyDecision $authorization, ?\BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace $trace = null): \BlueFission\Automata\Strategy\Routing\StrategyRouteResult {
                return new \BlueFission\Automata\Strategy\Routing\StrategyRouteResult(['code' => 'custom']);
            }
        };
        self::assertSame('custom', $router->route(request: new StrategyRouteRequest(), authorization: new AutonomyDecision())->code);
        self::assertFalse($router->supportsGuardedExecution());
        [, , , $strategy] = $this->fixture();
        self::assertArrayNotHasKey('admission', $strategy->start(input: null, runId: 'legacy')->result()->toArray());
    }

    public function testKnownFailureRetryGetsANewGuardButDenialConsumesNoExecutionAttempt(): void
    {
        [$run, $adapters] = $this->fixture(configs: ['a' => ['maximum_attempts' => 3, 'retry_codes' => ['miss']]]);
        $adapters['a']->work = static fn () => new StrategyAdapterResult(['status' => 'failed', 'code' => 'miss']);
        $run->execute('a');
        self::assertContains('a', $run->ready());
        $adapters['a']->suspend = 'estimate';
        $retry = new Fiber(fn () => $run->execute('a'));
        $retry->start(); $run->cancel(); $retry->resume();
        $record = $run->result()->toArray();
        self::assertSame(2, $record['admission']['scheduling_attempts']);
        self::assertSame(1, $record['admission']['execution_attempts']);
        self::assertSame(1, $record['admission']['adapter_invocations']);
        self::assertCount(2, $record['nodes']['a']['attempts']);
        self::assertFalse($record['nodes']['a']['attempts'][1]['executed']);
    }

    public function testLateReturnAfterDeadlineDoesNotUnlockDependentWork(): void
    {
        $now = 0;
        [$run, $adapters] = $this->fixture(clock: static function () use (&$now) { return $now; }, deadline: 5);
        $adapters['a']->suspend = 'execute';
        $fiber = new Fiber(fn () => $run->execute('a')); $fiber->start();
        $now = 5;
        self::assertFalse($run->result()->toArray()['settled']);
        self::assertSame(1, $run->result()->toArray()['admission']['in_flight']);
        $fiber->resume();
        $record = $run->result()->toArray();
        self::assertTrue($record['settled']);
        self::assertSame('expired', $record['status']);
        self::assertTrue($record['nodes']['a']['attempts'][0]['admission']['deadline_observed']);
        self::assertTrue($record['nodes']['a']['attempts'][0]['executed']);
        self::assertSame(0, $adapters['join']->calls);
    }

    public function testDecreasingClockAndPreflightMutationCannotDispatch(): void
    {
        $now = 10;
        [$run, $adapters] = $this->fixture(clock: static function () use (&$now) { return $now; });
        $run->ready(); $now = 9;
        self::assertSame([], $run->ready());
        self::assertSame('denied', $run->result()->status());
        self::assertSame(0, $adapters['a']->calls);
        [$run, $adapters] = $this->fixture();
        $adapters['a']->mutate = true;
        $run->execute('a');
        self::assertSame(0, $adapters['a']->calls);
        self::assertSame('dispatch_request_changed', $run->result()->toArray()['nodes']['a']['code']);
    }

    public function testNonSerializableAdvisorMutationSettlesWithoutLosingOriginalIdentity(): void
    {
        [$run, $adapters, $router] = $this->fixture();
        $router->mutateAdvice = true;
        $run->execute('a');
        $record = $run->result()->toArray();
        self::assertSame('denied', $record['nodes']['a']['status']);
        self::assertSame('dispatch_request_changed', $record['nodes']['a']['code']);
        self::assertSame(0, $record['admission']['in_flight']);
        self::assertSame(0, $adapters['a']->calls);
        self::assertSame($router->originalDigest, $record['nodes']['a']['attempts'][0]['admission']['request_digest']);
        self::assertSame('skipped', $record['nodes']['join']['status']);
    }

    public function testTypedDecisionsAndContextDoNotShareReferences(): void
    {
        foreach ([1, 'true', null, []] as $invalid) {
            try { new StrategyDispatchDecision($invalid); self::fail('Coerced decision'); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
        $value = 'original';
        $context = new StrategyDispatchContext(['nested' => ['value' => &$value]]);
        $value = 'changed';
        $snapshot = $context->toArray();
        self::assertSame('original', $snapshot['nested']['value']);
        $snapshot['nested']['value'] = 'export mutation';
        self::assertSame('original', $context->toArray()['nested']['value']);
        $policy = new WorkflowAdmissionPolicy(2, 'reviewed');
        try { $policy->maximumInFlight = 3; self::fail('Mutable policy'); }
        catch (\Error) { self::assertSame(2, $policy->maximumInFlight); }
    }

    public function testKnownFailureRetryAndLateCancellationRetainActualExecutionCounts(): void
    {
        [$run, $adapters] = $this->fixture(configs: ['a' => ['maximum_attempts' => 2, 'retry_codes' => ['miss']]]);
        $adapters['a']->work = static fn () => new StrategyAdapterResult(['status' => 'failed', 'code' => 'miss']);
        $run->execute('a');
        $adapters['a']->work = null;
        $adapters['a']->suspend = 'execute';
        $fiber = new Fiber(fn () => $run->execute('a')); $fiber->start();
        $run->cancel();
        self::assertFalse($run->result()->toArray()['settled']);
        $fiber->resume();
        $record = $run->result()->toArray();
        self::assertSame('cancelled', $record['status']);
        self::assertTrue($record['settled']);
        self::assertSame(2, $record['admission']['execution_attempts']);
        self::assertSame(2, $record['admission']['adapter_invocations']);
        self::assertTrue($record['nodes']['a']['attempts'][1]['admission']['cancel_observed']);
        self::assertSame('completed', $record['nodes']['a']['attempts'][1]['status']);
        self::assertSame(0, $adapters['join']->calls);
    }
}

/** Test-only synthetic read; every suspension location is explicitly selected. */
final class AdmissionProbe implements IStrategyRouteAdapter
{
    public string $suspend = '';
    public int $calls = 0;
    public ?\Closure $work = null;
    public bool $mutate = false;
    public function __construct(private string $id) {}
    public function definition(): StrategyDefinition { return new StrategyDefinition(['id' => $this->id, 'version' => '1', 'capability_id' => 'read', 'capability_version' => '1', 'mode' => 'deterministic', 'availability' => 'available', 'side_effect_free' => true]); }
    private function pause(string $phase): void { if ($this->suspend === $phase) { $this->suspend = ''; Fiber::suspend($phase); } }
    public function eligibility(StrategyRouteRequest $request): StrategyEligibility { $this->pause('eligibility'); if ($this->mutate) { $request->input = ['changed' => true]; } return new StrategyEligibility(['eligible' => true]); }
    public function estimate(StrategyRouteRequest $request): StrategyUsage { $this->pause('estimate'); return new StrategyUsage(['invocations' => 1]); }
    public function execute(StrategyRouteRequest $request): StrategyAdapterResult {
        ++$this->calls; $this->pause('execute');
        return $this->work ? ($this->work)($request) : new StrategyAdapterResult(['status' => 'completed', 'code' => 'completed', 'output' => $this->id === 'join' ? $request->input : $this->id]);
    }
}

final class AdmissionRouter extends StrategyRouter
{
    public bool $suspendAdvice = false;
    public bool $mutateAdvice = false;
    public ?string $originalDigest = null;
    public function supportsGuardedExecution(): bool { return true; }
    protected function orderedCandidates(StrategyRouteRequest $request): array {
        if ($this->suspendAdvice) { $this->suspendAdvice = false; Fiber::suspend('advice'); }
        if ($this->mutateAdvice) {
            $this->originalDigest = RecordSnapshot::fingerprint($request->toArray());
            $request->input = new \stdClass();
        }
        return parent::orderedCandidates($request);
    }
}
