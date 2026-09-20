<?php

namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\ScriptStrategy;
use BlueFission\Automata\Strategy\Script\ScriptExecution;
use BlueFission\Automata\Strategy\Routing\Adapter\ScriptRouteAdapter;
use BlueFission\Automata\Strategy\Routing\{StrategyRouter, StrategyRouteRequest};
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Parsing\{Parser, Element};
use BlueFission\Parsing\Contracts\IGenerator;
use BlueFission\Parsing\Contracts\IRenderableElement;
use BlueFission\Parsing\Registry\{TagRegistry, RendererRegistry, PreparerRegistry, ExecutorRegistry};
use PHPUnit\Framework\TestCase;
use LogicException;
use RuntimeException;

/** Real parser proof plus adversarial lifecycle checks at the host boundary. */
final class ScriptStrategyTest extends TestCase
{
    /** Reuse existing parser registries; strategy code must never mutate them. */
    protected function setUp(): void
    {
        TagRegistry::registerDefaults();
        RendererRegistry::registerDefaults();
        PreparerRegistry::registerDefaults();
        ExecutorRegistry::registerDefaults();
    }

    /** Decisions bind each exact operation and actor rather than grant a blanket boolean. */
    public static function approve(array $request): AutonomyDecision
    {
        return new AutonomyDecision(['allowed' => true, 'subject_id' => $request['subject_id'],
            'capability_id' => $request['capability_id'], 'capability_version' => $request['capability_version']]);
    }

    /** Use actual variable interpolation, preserving zero and fresh state on reuse. */
    public function testFreshParserAndNormalizedResults(): void
    {
        $strategy = $this->strategy();
        $this->assertSame('Hello 0', $strategy->predict(['name' => '0']));
        $first = $strategy->lastResult()->toArray();
        $this->assertSame('Hello next', $strategy->predict(['name' => 'next']));
        $second = $strategy->lastResult()->toArray();
        $this->assertNotSame($first['run_id'], $second['run_id']);
        $this->assertNotSame($first['input_fingerprint'], $second['input_fingerprint']);
        $this->assertSame(hash('sha256', 'Hello {$name}'), $second['source_sha256']);
        $this->assertNull($second['cost']);
        $this->assertNull($second['confidence']);
    }

    /** Initial denial prevents even parser preparation, not only output delivery. */
    public function testDeniedScriptNeverPrepares(): void
    {
        $calls = 0;
        $strategy = $this->strategy(function () use (&$calls) { $calls++; return new Parser('bad'); }, static fn () => new AutonomyDecision());
        $this->assertSame('denied', $strategy->run([])->status());
        $this->assertSame(0, $calls);
    }

    /** Early exit is terminal and skips subsequent generation and preparation work. */
    public function testEarlyExitClosesCapturedHandle(): void
    {
        $handle = null;
        $strategy = $this->strategy(function ($source, $input, $run) use (&$handle) {
            $handle = $run;
            $run->finish('0');
            throw new RuntimeException('unreachable');
        });
        $result = $strategy->run([]);
        $this->assertSame('early_exit', $result->status());
        $this->assertSame('0', $result->output());
        $this->expectException(LogicException::class);
        $handle->generate(new Element('slot', '', ''));
    }

    /** Catching a denied slot cannot turn the enclosing script into a success. */
    public function testCaughtSlotDenialRemainsTerminal(): void
    {
        $generator = $this->generator();
        $strategy = $this->strategy(function ($source, $input, $run) {
            try { $run->generate(new Element('slot', '', 'prompt')); } catch (RuntimeException) {}
            return new Parser('fabricated success');
        }, static fn ($r) => $r['operation'] === 'execute' ? self::approve($r) : new AutonomyDecision(), $generator);
        $result = $strategy->run([]);
        $this->assertSame('denied', $result->status());
        $this->assertNull($result->output());
        $this->assertSame(0, $generator->calls);
    }

    /** Denial after preparation must prevent rendering, even when the factory catches it. */
    public function testCaughtDenialNeverRenders(): void
    {
        $renders = 0;
        $strategy = $this->strategy(function ($s, $i, $run) use (&$renders) {
            try { $run->generate(new Element('slot', '', '')); } catch (\Throwable) {}
            return new class($renders) implements IRenderableElement {
                public function __construct(private int &$renders) {}
                public function render(): string { $this->renders++; return 'bad'; }
            };
        }, null, null);
        $this->assertSame('denied', $strategy->run([])->status());
        $this->assertSame(0, $renders);
    }

    /** Swallowing an early-exit signal still cannot authorize subsequent rendering. */
    public function testCaughtEarlyExitSkipsRenderer(): void
    {
        $renders = 0;
        $strategy = $this->strategy(function ($s, $i, $run) use (&$renders) {
            try { $run->finish('done'); } catch (\Throwable) {}
            return new class($renders) implements IRenderableElement {
                public function __construct(private int &$renders) {}
                public function render(): string { $this->renders++; return 'bad'; }
            };
        });
        $this->assertSame('done', $strategy->predict([]));
        $this->assertSame(0, $renders);
    }

    /** A successful slot result remains in receipts when cancellation arrives in flight. */
    public function testCancellationRetainsGenerationReceipt(): void
    {
        $handle = null;
        $generator = new class($handle) implements IGenerator {
            public function __construct(private mixed &$handle) {}
            public function generate(Element $element): string { $this->handle->cancel(); return 'delivered'; }
        };
        $strategy = $this->strategy(function ($s, $i, $run) use (&$handle) {
            $handle = $run;
            $run->generate(new Element('slot', '', ''));
            return new Parser('hidden');
        }, null, $generator);
        $result = $strategy->run([])->toArray();
        $this->assertSame('cancelled', $result['status']);
        $this->assertNull($result['output']);
        $this->assertSame('completed', $result['generations'][0]['status']);
        $this->assertSame(hash('sha256', 'delivered'), $result['generations'][0]['output_sha256']);
    }

    /** Generator failure cannot be swallowed, retried or represented as empty success. */
    public function testUncertainGeneratorStopsLaterCalls(): void
    {
        $generator = $this->generator(true);
        $strategy = $this->strategy(function ($s, $i, $run) {
            for ($i = 0; $i < 2; $i++) {
                try { $run->generate(new Element('slot', '', '')); } catch (\Throwable) {}
            }
            return new Parser('bad');
        }, null, $generator);
        $result = $strategy->run([])->toArray();
        $this->assertSame('uncertain', $result['status']);
        $this->assertSame(1, $generator->calls);
        $this->assertSame('uncertain', $result['generations'][0]['status']);
    }

    /** The call limit is enforced before the next generator dispatch. */
    public function testSlotBudgetAndFreshAuthorization(): void
    {
        $generator = $this->generator();
        $requests = [];
        $strategy = $this->strategy(function ($s, $i, $run) {
            $run->generate(new Element('slot', '', 'first'));
            $run->generate(new Element('slot', '', 'second'));
            return new Parser('never');
        }, function ($r) use (&$requests) { $requests[] = $r; return self::approve($r); }, $generator, 1);
        $this->assertSame('denied', $strategy->run([])->status());
        $this->assertSame(1, $generator->calls);
        $this->assertSame(['execute', 'generate'], array_column($requests, 'operation'));
        $this->assertSame(1, $requests[1]['generation_index']);
    }

    /** Reusing a parser would retain variables/element execution state and is rejected. */
    public function testReusedParserIsRejected(): void
    {
        $parser = new Parser('same');
        $strategy = $this->strategy(static fn () => $parser);
        $this->assertTrue($strategy->run([])->succeeded());
        $this->assertSame('uncertain', $strategy->run([])->status());
    }

    /** Reentry cannot overwrite the outer result, and later independent runs still work. */
    public function testReentryIsRejected(): void
    {
        $strategy = null;
        $strategy = $this->strategy(function () use (&$strategy) {
            try { $strategy->run([]); $this->fail('reentry allowed'); } catch (LogicException) {}
            return new Parser('outer');
        });
        $this->assertSame('outer', $strategy->predict([]));
        $this->assertSame('outer', $strategy->predict([]));
    }

    /** A grant for another actor or capability never starts the script. */
    public function testMismatchedGrantIsDenied(): void
    {
        $strategy = $this->strategy(null, static fn ($r) => new AutonomyDecision([
            'allowed' => true, 'subject_id' => 'other', 'capability_id' => $r['capability_id'], 'capability_version' => '1']));
        $this->assertSame('denied', $strategy->run([])->status());
    }

    /** The real router can select a reviewed side-effect-free script and preserve its output. */
    public function testRouterAndTraceUseScriptIdentity(): void
    {
        $trace = new TaskTrace('script-test');
        $strategy = new ScriptStrategy('greeting', '1', 'Hello {$name}', self::parser(...), self::approve(...), 'actor', trace: $trace);
        $adapter = new ScriptRouteAdapter($strategy, sideEffectFree: true);
        $router = new StrategyRouter([$adapter]);
        $request = new StrategyRouteRequest(['id' => 'route', 'subject_id' => 'actor',
            'capability_id' => 'greeting.execute', 'capability_version' => '1',
            'candidates' => [['id' => 'greeting', 'version' => '1']], 'input' => ['name' => 'Ada']]);
        $result = $router->route($request, self::approve($request->toArray()));
        $this->assertSame('completed', $result->status);
        $this->assertSame('Hello Ada', $result->output);
        $this->assertCount(1, $trace->toArray()['spans']);
        $this->assertFalse($adapter->eligibility(new StrategyRouteRequest(['limits' => ['max_cost' => 0]]))->eligible);
    }

    /** Constructing the adapter alone must not silently claim scripts are pure. */
    public function testPurityAndTrainingAreNotInvented(): void
    {
        $strategy = $this->strategy();
        $this->assertFalse((new ScriptRouteAdapter($strategy))->definition()->side_effect_free);
        $this->expectException(LogicException::class);
        $strategy->accuracy();
    }

    /** Reports are detached and failures do not poison independently admitted runs. */
    public function testReportsAreDetachedAndFailuresDoNotLeakIntoLaterRuns(): void
    {
        $strategy = $this->strategy(static function ($source, $input, $run) {
            if ($input === 'fail') { throw new RuntimeException('private failure detail'); }
            return new Parser('good');
        });
        $failure = $strategy->run('fail')->toArray();
        $this->assertSame('uncertain', $failure['status']);
        $this->assertSame(RuntimeException::class, $failure['diagnostic']);
        $this->assertStringNotContainsString('private failure detail', json_encode($failure));
        $result = $strategy->run('ok');
        $export = $result->toArray();
        $export['output'] = 'changed';
        $this->assertSame('good', $result->output());
        $this->assertSame('completed', $result->status());
    }

    /** Reentrant generation cannot dispatch another call inside the active one. */
    public function testGeneratorReentryIsRejected(): void
    {
        $handle = null;
        $generator = new class($handle) implements IGenerator {
            public int $calls = 0;
            public function __construct(private mixed &$handle) {}
            public function generate(Element $element): string {
                $this->calls++;
                try { $this->handle->generate($element); } catch (LogicException) { return 'guarded'; }
                throw new RuntimeException('reentry was allowed');
            }
        };
        $strategy = $this->strategy(function ($s, $i, $run) use (&$handle) {
            $handle = $run;
            return new Parser($run->generate(new Element('slot', '', '')));
        }, null, $generator);
        $this->assertSame('guarded', $strategy->predict([]));
        $this->assertSame(1, $generator->calls);
    }

    /** Trace observer failure after execution must not invite replay. */
    public function testTraceFailureIsReportedWithoutErasingOutput(): void
    {
        $trace = new class('broken-observer') extends TaskTrace {
            public function startSpan(string $kind, string $name, array $metadata = [], ?string $parentSpanId = null): \BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan {
                throw new RuntimeException('trace unavailable');
            }
        };
        $strategy = new ScriptStrategy('trace', '1', 'done', self::parser(...), self::approve(...), 'actor', trace: $trace);
        $this->assertSame('done', $strategy->predict([]));
        $this->assertSame('failed', $strategy->lastResult()->toArray()['telemetry_status']);
    }

    /** Uncertain execution aborts escalation to an otherwise eligible candidate. */
    public function testUncertainScriptDoesNotEscalate(): void
    {
        $calls = 0;
        $fallback = new class($calls) implements \BlueFission\Automata\Strategy\Routing\IStrategyRouteAdapter {
            public function __construct(private int &$calls) {}
            public function definition(): \BlueFission\Automata\Strategy\Routing\StrategyDefinition {
                return new \BlueFission\Automata\Strategy\Routing\StrategyDefinition(['id' => 'fallback', 'version' => '1',
                    'capability_id' => 'greeting.execute', 'capability_version' => '1', 'side_effect_free' => true, 'availability' => 'available']);
            }
            public function eligibility(StrategyRouteRequest $r): \BlueFission\Automata\Strategy\Routing\StrategyEligibility {
                return new \BlueFission\Automata\Strategy\Routing\StrategyEligibility(['eligible' => true]);
            }
            public function estimate(StrategyRouteRequest $r): \BlueFission\Automata\Strategy\Routing\StrategyUsage {
                return new \BlueFission\Automata\Strategy\Routing\StrategyUsage(['invocations' => 1]);
            }
            public function execute(StrategyRouteRequest $r): \BlueFission\Automata\Strategy\Routing\StrategyAdapterResult {
                $this->calls++;
                return new \BlueFission\Automata\Strategy\Routing\StrategyAdapterResult(['status' => 'completed', 'output' => 'fallback']);
            }
        };
        $requestData = ['id' => 'escalation-test', 'subject_id' => 'actor', 'capability_id' => 'greeting.execute', 'capability_version' => '1',
            'escalation_policy' => 'next_eligible', 'input' => []];
        $control = new StrategyRouteRequest($requestData + ['candidates' => [['id' => 'fallback', 'version' => '1']]]);
        $this->assertSame('completed', (new StrategyRouter([$fallback]))->route($control, self::approve($control->toArray()))->status);
        $this->assertSame(1, $calls);
        $calls = 0;
        $script = $this->strategy(static fn () => throw new RuntimeException('unknown effects'));
        $request = new StrategyRouteRequest($requestData + ['candidates' => [
            ['id' => 'greeting', 'version' => '1'], ['id' => 'fallback', 'version' => '1']]]);
        $result = (new StrategyRouter([new ScriptRouteAdapter($script, true), $fallback]))->route($request, self::approve($request->toArray()));
        $this->assertSame('failed', $result->status);
        $this->assertSame('uncertain', $script->lastResult()->status());
        $this->assertSame(0, $calls);
    }

    /** Authorization ceilings must reach eligibility, not only numeric usage checks. */
    public function testGrantCostLimitCannotUseUnknownCostAsZero(): void
    {
        $calls = 0;
        $strategy = $this->strategy(function () use (&$calls) { $calls++; return new Parser('bad'); });
        $request = new StrategyRouteRequest(['id' => 'cost-test', 'subject_id' => 'actor',
            'capability_id' => 'greeting.execute', 'capability_version' => '1',
            'candidates' => [['id' => 'greeting', 'version' => '1']], 'input' => []]);
        $grant = self::approve($request->toArray());
        $grant->limits = ['max_cost' => 0];
        $result = (new StrategyRouter([new ScriptRouteAdapter($strategy, true)]))->route($request, $grant);
        $this->assertSame('exhausted', $result->status);
        $this->assertSame(0, $calls);
        $this->assertSame([], $request->limits);
    }

    /** Direct execution also refuses unknown monetary ceilings on its own grant. */
    public function testDirectAuthorizationBudgetIsNotIgnored(): void
    {
        $calls = 0;
        $strategy = $this->strategy(function () use (&$calls) { $calls++; return new Parser('bad'); },
            static function ($request) { $decision = self::approve($request); $decision->limits = ['max_cost' => 0]; return $decision; });
        $this->assertSame('denied', $strategy->run([])->status());
        $this->assertSame(0, $calls);
    }

    /** Effective limits retain explicit deny-all modes and scalar false input. */
    public function testEffectiveRequestPreservesEmptyData(): void
    {
        $strategy = $this->strategy(static function ($s, $input) { return new Parser($input === false ? 'false retained' : 'wrong'); });
        $router = new StrategyRouter([new ScriptRouteAdapter($strategy, true)]);
        $data = ['id' => 'false-input', 'subject_id' => 'actor', 'capability_id' => 'greeting.execute',
            'capability_version' => '1', 'input' => false, 'candidates' => [['id' => 'greeting', 'version' => '1']]];
        $request = new StrategyRouteRequest($data);
        $this->assertSame('false retained', $router->route($request, self::approve($request->toArray()))->output);
        $denied = new StrategyRouteRequest($data + ['allowed_modes' => []]);
        $this->assertSame('exhausted', $router->route($denied, self::approve($denied->toArray()))->status);
    }

    /** Shared host assembly keeps source separate from input values. */
    public static function parser(string $source, mixed $input, ScriptExecution $run): Parser
    {
        $parser = new Parser($source);
        $parser->setVariables($input);
        return $parser;
    }

    /** Prepare a real strategy, overriding only the boundary under test. */
    private function strategy(?callable $prepare = null, ?callable $authorize = null, ?IGenerator $generator = null, int $limit = 8): ScriptStrategy
    {
        return new ScriptStrategy('greeting', '1', 'Hello {$name}', $prepare ?? self::parser(...),
            $authorize ?? self::approve(...), 'actor', $generator, $limit);
    }

    /** A deterministic generator exercises the real IGenerator seam without provider spend. */
    private function generator(bool $fail = false): IGenerator
    {
        return new class($fail) implements IGenerator {
            public int $calls = 0;
            public function __construct(private bool $fail) {}
            public function generate(Element $element): string { $this->calls++; if ($this->fail) { throw new RuntimeException('transport lost'); } return 'generated'; }
        };
    }
}
