<?php

namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\DepthFirstTraceMethod;
use BlueFission\Automata\DecisionTree\Node;
use BlueFission\Automata\Strategy\Routing\Adapter\DecisionTreeRouteAdapter;
use BlueFission\Automata\Strategy\Routing\IStrategyRouteAdapter;
use BlueFission\Automata\Strategy\Routing\StrategyAdapterResult;
use BlueFission\Automata\Strategy\Routing\StrategyDefinition;
use BlueFission\Automata\Strategy\Routing\StrategyEligibility;
use BlueFission\Automata\Strategy\Routing\StrategyRouteRequest;
use BlueFission\Automata\Strategy\Routing\StrategyRouteResult;
use BlueFission\Automata\Strategy\Routing\StrategyRouter;
use BlueFission\Automata\Strategy\Routing\StrategyUsage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StrategyRouterTest extends TestCase
{
    public function testDeterministicCandidatePrecedesLearnedCandidateAndRecordsTrace(): void
    {
        $learned = $this->adapter('learned.rank', StrategyDefinition::MODE_LEARNED, [
            'output' => ['decision' => 'learned'],
        ]);
        $deterministic = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC, [
            'output' => ['decision' => 'ground'],
            'usage' => ['cost' => 0.0, 'latency_ms' => 4, 'energy' => 0.2, 'invocations' => 1],
        ]);
        $trace = new TaskTrace('trace-strategy-1');

        $result = (new StrategyRouter([$learned, $deterministic]))->route(
            $this->request([
                'candidates' => [
                    ['id' => 'learned.rank', 'version' => '1.0'],
                    ['id' => 'tree.dispatch', 'version' => '1.0'],
                ],
                'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC, StrategyDefinition::MODE_LEARNED],
            ]),
            $this->authorization(),
            $trace
        );

        $this->assertSame(StrategyRouteResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('tree.dispatch', $result->selected_strategy['id']);
        $this->assertSame(['decision' => 'ground'], $result->output);
        $this->assertSame(1, $deterministic->executions);
        $this->assertSame(0, $learned->executions);
        $this->assertSame('trace-strategy-1', $result->toArray()['trace_id']);
        $this->assertSame('strategy', $trace->toArray()['spans'][0]['kind']);
        $this->assertSame('completed', $trace->toArray()['spans'][0]['status']);
    }

    public function testAuthorizationFailsClosedBeforeAdapterExecution(): void
    {
        $adapter = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC);
        $router = new StrategyRouter([$adapter]);

        $denied = $router->route($this->request(), $this->authorization([
            'allowed' => false,
            'code' => AutonomyDecision::CODE_APPROVAL_REQUIRED,
        ]));
        $mismatched = $router->route($this->request(), $this->authorization([
            'capability_version' => '2.0',
        ]));

        $this->assertSame(StrategyRouteResult::STATUS_DENIED, $denied->status);
        $this->assertSame(StrategyRouter::CODE_AUTHORIZATION_DENIED, $denied->code);
        $this->assertSame(StrategyRouter::CODE_AUTHORIZATION_MISMATCH, $mismatched->code);
        $this->assertSame(0, $adapter->executions);
    }

    public function testMalformedRequestFailsBeforeAuthorityOrAdapterEvaluation(): void
    {
        $adapter = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC);

        $result = (new StrategyRouter([$adapter]))->route(
            $this->request(['subject_id' => '', 'candidates' => []]),
            $this->authorization()
        );

        $this->assertSame(StrategyRouteResult::STATUS_DENIED, $result->status);
        $this->assertSame(StrategyRouter::CODE_INVALID_REQUEST, $result->code);
        $this->assertSame(0, $adapter->executions);
    }

    public function testStrategyDefinitionsRequireExplicitSideEffectFreeDeclaration(): void
    {
        $definition = new StrategyDefinition([
            'id' => 'tree.dispatch',
            'version' => '1.0',
            'capability_id' => 'policy.route',
            'capability_version' => '1.0',
        ]);

        $this->assertFalse($definition->side_effect_free);
        $this->assertFalse($definition->toArray()['side_effect_free']);
    }

    public function testRoutingValuesExposeNativeTypedObjFields(): void
    {
        $request = $this->request();

        $request->selection_policy = StrategyRouteRequest::SELECTION_ADAPTIVE;
        $request->context_key = 'support.billing';

        $this->assertSame(StrategyRouteRequest::SELECTION_ADAPTIVE, $request->selection_policy);
        $this->assertSame('support.billing', $request->context_key);
        $this->assertSame('support.billing', $request->toArray()['context_key']);
    }

    public function testEligibilityAndEstimatedBudgetBlockExecution(): void
    {
        $ineligible = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC, [], [
            'eligible' => false,
            'code' => 'predicate_failed',
            'reasons' => ['fixture does not match'],
        ]);
        $expensive = $this->adapter('learned.rank', StrategyDefinition::MODE_LEARNED, [], [], [
            'cost' => 3.0,
            'latency_ms' => 20,
            'energy' => 2.0,
            'invocations' => 1,
        ]);

        $result = (new StrategyRouter([$ineligible, $expensive]))->route(
            $this->request([
                'candidates' => [
                    ['id' => 'tree.dispatch', 'version' => '1.0'],
                    ['id' => 'learned.rank', 'version' => '1.0'],
                ],
                'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC, StrategyDefinition::MODE_LEARNED],
                'limits' => ['max_cost' => 1.0, 'max_latency_ms' => 100, 'max_energy' => 4.0, 'max_invocations' => 2],
            ]),
            $this->authorization()
        );

        $this->assertSame(StrategyRouteResult::STATUS_EXHAUSTED, $result->status);
        $this->assertSame('predicate_failed', $result->attempts[0]['code']);
        $this->assertSame(StrategyRouter::CODE_ESTIMATED_BUDGET_EXCEEDED, $result->attempts[1]['code']);
        $this->assertSame(0, $ineligible->executions);
        $this->assertSame(0, $expensive->executions);
    }

    public function testExplicitEscalationCanReachGenerativeWithinCumulativeBudget(): void
    {
        $deterministic = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC, [
            'status' => StrategyAdapterResult::STATUS_FAILED,
            'code' => 'no_match',
            'usage' => ['cost' => 0.2, 'latency_ms' => 5, 'energy' => 0.1, 'invocations' => 1],
        ]);
        $generative = $this->adapter('provider.generate', StrategyDefinition::MODE_GENERATIVE, [
            'output' => ['decision' => 'review'],
            'usage' => ['cost' => 0.4, 'latency_ms' => 30, 'energy' => 0.3, 'invocations' => 1],
        ], [], [
            'cost' => 0.4,
            'latency_ms' => 30,
            'energy' => 0.3,
            'invocations' => 1,
        ]);

        $result = (new StrategyRouter([$deterministic, $generative]))->route(
            $this->request([
                'candidates' => [
                    ['id' => 'tree.dispatch', 'version' => '1.0'],
                    ['id' => 'provider.generate', 'version' => '1.0'],
                ],
                'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC, StrategyDefinition::MODE_GENERATIVE],
                'escalation_policy' => StrategyRouteRequest::ESCALATE_NEXT_ELIGIBLE,
                'limits' => ['max_cost' => 1.0, 'max_latency_ms' => 100, 'max_energy' => 1.0, 'max_invocations' => 2],
            ]),
            $this->authorization()
        );

        $this->assertSame(StrategyRouteResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('provider.generate', $result->selected_strategy['id']);
        $this->assertEqualsWithDelta(0.6, $result->usage['cost'], 0.0001);
        $this->assertSame(2, $result->usage['invocations']);
        $this->assertSame('tree.dispatch', $result->escalation_history[0]['from']);
        $this->assertSame('provider.generate', $result->escalation_history[0]['to']);
    }

    public function testGenerativeModeAndSideEffectsRequireExplicitPolicy(): void
    {
        $generative = $this->adapter('provider.generate', StrategyDefinition::MODE_GENERATIVE);
        $sideEffects = $this->adapter('tool.write', StrategyDefinition::MODE_DETERMINISTIC, [], [], [], [
            'side_effect_free' => false,
        ]);

        $result = (new StrategyRouter([$generative, $sideEffects]))->route(
            $this->request([
                'candidates' => [
                    ['id' => 'provider.generate', 'version' => '1.0'],
                    ['id' => 'tool.write', 'version' => '1.0'],
                ],
            ]),
            $this->authorization()
        );

        $attempts = [];
        foreach ($result->attempts as $attempt) {
            $attempts[$attempt['strategy_id']] = $attempt;
        }

        $this->assertSame(StrategyRouter::CODE_MODE_NOT_ALLOWED, $attempts['provider.generate']['code']);
        $this->assertSame(StrategyRouter::CODE_SIDE_EFFECTS_NOT_ALLOWED, $attempts['tool.write']['code']);
        $this->assertSame(0, $generative->executions);
        $this->assertSame(0, $sideEffects->executions);
    }

    public function testActualUsageOverrunFailsClosed(): void
    {
        $adapter = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC, [
            'usage' => ['cost' => 2.0, 'latency_ms' => 5, 'energy' => 0.1, 'invocations' => 1],
        ], [], [
            'cost' => 0.1,
            'latency_ms' => 5,
            'energy' => 0.1,
            'invocations' => 1,
        ]);

        $result = (new StrategyRouter([$adapter]))->route(
            $this->request(['limits' => ['max_cost' => 1.0]]),
            $this->authorization()
        );

        $this->assertSame(StrategyRouteResult::STATUS_FAILED, $result->status);
        $this->assertSame(StrategyRouter::CODE_ACTUAL_BUDGET_EXCEEDED, $result->code);
        $this->assertSame(1, $adapter->executions);
    }

    public function testAuthorizationLimitsNarrowRequestBudgets(): void
    {
        $adapter = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC, [], [], [
            'cost' => 0.75,
        ]);

        $result = (new StrategyRouter([$adapter]))->route(
            $this->request(['limits' => ['max_cost' => 1.0]]),
            $this->authorization(['limits' => ['max_cost' => 0.5]])
        );

        $this->assertSame(StrategyRouteResult::STATUS_EXHAUSTED, $result->status);
        $this->assertSame(StrategyRouter::CODE_ESTIMATED_BUDGET_EXCEEDED, $result->attempts[0]['code']);
        $this->assertSame(0.5, $result->toArray()['limits']['max_cost']);
        $this->assertSame(0, $adapter->executions);
    }

    public function testEveryProjectedExecutionConsumesAtLeastOneInvocation(): void
    {
        $adapter = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC, [], [], [
            'invocations' => 0,
        ]);

        $result = (new StrategyRouter([$adapter]))->route(
            $this->request(['limits' => ['max_invocations' => 0]]),
            $this->authorization()
        );

        $this->assertSame(StrategyRouter::CODE_ESTIMATED_BUDGET_EXCEEDED, $result->attempts[0]['code']);
        $this->assertSame(1, $result->attempts[0]['estimate']['invocations']);
        $this->assertSame(0, $adapter->executions);
    }

    public function testAdapterExceptionsBecomeStructuredFailedAttempts(): void
    {
        $adapter = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC);
        $adapter->throwOnExecute = true;

        $result = (new StrategyRouter([$adapter]))->route(
            $this->request(),
            $this->authorization()
        );

        $this->assertSame(StrategyRouteResult::STATUS_FAILED, $result->status);
        $this->assertSame(StrategyRouter::CODE_EXECUTION_EXCEPTION, $result->code);
        $this->assertSame(StrategyRouter::CODE_EXECUTION_EXCEPTION, $result->attempts[0]['code']);
        $this->assertSame('adapter failed', $result->attempts[0]['diagnostics'][0]['message']);
    }

    public function testExecutionExceptionDoesNotEscalateWithUnknownActualUsage(): void
    {
        $failing = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC);
        $failing->throwOnExecute = true;
        $fallback = $this->adapter('provider.generate', StrategyDefinition::MODE_GENERATIVE);

        $result = (new StrategyRouter([$failing, $fallback]))->route(
            $this->request([
                'candidates' => [
                    ['id' => 'tree.dispatch', 'version' => '1.0'],
                    ['id' => 'provider.generate', 'version' => '1.0'],
                ],
                'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC, StrategyDefinition::MODE_GENERATIVE],
                'escalation_policy' => StrategyRouteRequest::ESCALATE_NEXT_ELIGIBLE,
            ]),
            $this->authorization()
        );

        $this->assertSame(StrategyRouter::CODE_EXECUTION_EXCEPTION, $result->code);
        $this->assertSame(0, $fallback->executions);
        $this->assertSame([], $result->escalation_history);
    }

    public function testUnknownExactVersionProducesStableExhaustedResultSchema(): void
    {
        $result = (new StrategyRouter())->route(
            $this->request(['candidates' => [['id' => 'tree.dispatch', 'version' => '2.0']]]),
            $this->authorization()
        );
        $data = $result->toArray();

        $this->assertSame(StrategyRouteResult::STATUS_EXHAUSTED, $result->status);
        $this->assertSame(StrategyRouter::CODE_UNKNOWN_STRATEGY, $result->attempts[0]['code']);
        $this->assertNull($data['selected_strategy']);
        $this->assertSame([], $data['escalation_history']);
        $this->assertSame(0.0, $data['usage']['cost']);
        $this->assertTrue($data['authorization']['allowed']);
        $this->assertFalse($data['escalated']);
    }

    public function testDecisionTreeAdapterRoutesRealEngineOutputAndTrace(): void
    {
        $evaluate = static fn (array $value, array $children, array $state): float =>
            (float)($value['base_score'] ?? 0)
            + (($value['decision'] ?? '') === ($state['preferred'] ?? '') ? 10.0 : 0.0);
        $root = new Node(['id' => 'root', 'decision' => 'review', 'base_score' => 0], $evaluate);
        $ground = new Node(['id' => 'ground', 'decision' => 'ground', 'base_score' => 2], $evaluate);
        $air = new Node(['id' => 'air', 'decision' => 'air', 'base_score' => 1], $evaluate);
        $root->addChild($ground);
        $root->addChild($air);
        $tree = new DecisionTree();
        $tree->setRoot($root);

        $adapter = new DecisionTreeRouteAdapter(
            $tree,
            new DepthFirstTraceMethod(),
            new StrategyDefinition([
                'id' => 'tree.dispatch',
                'version' => '1.0',
                'capability_id' => 'policy.route',
                'capability_version' => '1.0',
                'family' => 'decision_tree',
                'mode' => StrategyDefinition::MODE_DETERMINISTIC,
                'side_effect_free' => true,
                'availability' => StrategyDefinition::AVAILABILITY_AVAILABLE,
            ]),
            new StrategyEligibility([
                'eligible' => true,
                'code' => StrategyEligibility::CODE_ELIGIBLE,
                'evidence' => [['source' => 'fixture-policy']],
            ]),
            new StrategyUsage(['latency_ms' => 1, 'energy' => 0.1, 'invocations' => 1])
        );

        $result = (new StrategyRouter([$adapter]))->route(
            $this->request(['input' => ['preferred' => 'air']]),
            $this->authorization()
        );

        $this->assertSame('air', $result->output['decision']['decision']);
        $this->assertSame(['root', 'air'], array_column($result->output['trace'], 'id'));
        $this->assertSame('fixture-policy', $result->attempts[0]['evidence'][0]['source']);
    }

    private function adapter(
        string $id,
        string $mode,
        array $result = [],
        array $eligibility = [],
        array $estimate = [],
        array $definition = []
    ): StrategyRouterTestAdapter {
        return new StrategyRouterTestAdapter(
            array_replace_recursive([
                'id' => $id,
                'version' => '1.0',
                'capability_id' => 'policy.route',
                'capability_version' => '1.0',
                'family' => $id,
                'mode' => $mode,
                'priority' => 100,
                'side_effect_free' => true,
                'availability' => StrategyDefinition::AVAILABILITY_AVAILABLE,
            ], $definition),
            array_replace_recursive([
                'eligible' => true,
                'code' => StrategyEligibility::CODE_ELIGIBLE,
            ], $eligibility),
            array_replace_recursive([
                'cost' => 0.0,
                'latency_ms' => 1,
                'energy' => 0.0,
                'invocations' => 1,
            ], $estimate),
            array_replace_recursive([
                'status' => StrategyAdapterResult::STATUS_COMPLETED,
                'code' => StrategyAdapterResult::CODE_COMPLETED,
                'output' => ['decision' => 'accepted'],
                'usage' => [
                    'cost' => 0.0,
                    'latency_ms' => 1,
                    'energy' => 0.0,
                    'invocations' => 1,
                ],
            ], $result)
        );
    }

    private function request(array $overrides = []): StrategyRouteRequest
    {
        return new StrategyRouteRequest(array_replace_recursive([
            'id' => 'route-1',
            'subject_id' => 'agent-policy',
            'capability_id' => 'policy.route',
            'capability_version' => '1.0',
            'candidates' => [['id' => 'tree.dispatch', 'version' => '1.0']],
            'input' => ['severity' => 'critical'],
            'fixtures' => [['id' => 'dispatch-policy-v1']],
            'limits' => [
                'max_cost' => 2.0,
                'max_latency_ms' => 100,
                'max_energy' => 2.0,
                'max_invocations' => 3,
            ],
            'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC],
            'deterministic_preferred' => true,
            'escalation_policy' => StrategyRouteRequest::ESCALATE_NONE,
            'correlation_id' => 'correlation-route-1',
            'trace_id' => 'trace-route-request-1',
        ], $overrides));
    }

    private function authorization(array $overrides = []): AutonomyDecision
    {
        return new AutonomyDecision(array_replace_recursive([
            'allowed' => true,
            'code' => AutonomyDecision::CODE_ALLOWED,
            'reason' => AutonomyDecision::CODE_ALLOWED,
            'packet_id' => 'autonomy-route-1',
            'subject_id' => 'agent-policy',
            'capability_id' => 'policy.route',
            'capability_version' => '1.0',
            'evidence' => [['source' => 'operator-approval']],
        ], $overrides));
    }
}

class StrategyRouterTestAdapter implements IStrategyRouteAdapter
{
    public int $executions = 0;
    public bool $throwOnExecute = false;

    public function __construct(
        private array $definitionData,
        private array $eligibilityData,
        private array $estimateData,
        private array $resultData
    ) {
    }

    public function definition(): StrategyDefinition
    {
        return new StrategyDefinition($this->definitionData);
    }

    public function eligibility(StrategyRouteRequest $request): StrategyEligibility
    {
        return new StrategyEligibility($this->eligibilityData);
    }

    public function estimate(StrategyRouteRequest $request): StrategyUsage
    {
        return new StrategyUsage($this->estimateData);
    }

    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        $this->executions++;
        if ($this->throwOnExecute) {
            throw new RuntimeException('adapter failed');
        }

        return new StrategyAdapterResult($this->resultData);
    }
}
