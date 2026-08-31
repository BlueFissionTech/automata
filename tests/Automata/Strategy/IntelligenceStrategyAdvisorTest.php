<?php

namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Intelligence;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Strategy\Routing\IStrategyRouteAdapter;
use BlueFission\Automata\Strategy\Routing\StrategyAdapterResult;
use BlueFission\Automata\Strategy\Routing\StrategyDefinition;
use BlueFission\Automata\Strategy\Routing\StrategyEligibility;
use BlueFission\Automata\Strategy\Routing\StrategyRouteRequest;
use BlueFission\Automata\Strategy\Routing\StrategyRouter;
use BlueFission\Automata\Strategy\Routing\StrategyUsage;
use PHPUnit\Framework\TestCase;

class IntelligenceStrategyAdvisorTest extends TestCase
{
    public function testAdaptiveEvidenceReordersDeclaredCandidatesWithinOnePolicyTier(): void
    {
        $intelligence = new Intelligence();
        $intelligence->recordStrategyFeedback('slow.tree', '1.0', [
            'successful' => false,
            'accuracy' => 0.2,
            'prediction_accuracy' => 0.1,
            'score' => 0.1,
            'latency_ms' => 200,
            'energy' => 0.5,
        ], 'incident-routing');
        $intelligence->recordStrategyFeedback('fast.tree', '1.0', [
            'successful' => true,
            'accuracy' => 0.9,
            'prediction_accuracy' => 0.95,
            'score' => 0.9,
            'latency_ms' => 5,
            'energy' => 0.1,
        ], 'incident-routing');

        $slow = $this->adapter('slow.tree', StrategyDefinition::MODE_DETERMINISTIC, 200);
        $fast = $this->adapter('fast.tree', StrategyDefinition::MODE_DETERMINISTIC, 5);
        $request = $this->request([
            'candidates' => [
                ['id' => 'slow.tree', 'version' => '1.0'],
                ['id' => 'fast.tree', 'version' => '1.0'],
            ],
            'selection_policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
            'context_key' => 'incident-routing',
        ]);

        $result = (new StrategyRouter([$slow, $fast], $intelligence))->route(
            $request,
            $this->authorization()
        );

        $this->assertSame('fast.tree', $result->selected_strategy['id']);
        $this->assertSame(0, $slow->executions);
        $this->assertSame(1, $fast->executions);
        $this->assertSame(StrategyRouteRequest::SELECTION_ADAPTIVE, $result->selection_advice['policy']);
        $this->assertGreaterThan(
            $result->selection_advice['rankings']['slow.tree@1.0']['score'],
            $result->selection_advice['rankings']['fast.tree@1.0']['score']
        );
    }

    public function testDeterministicPreferenceCannotBeOverriddenByAdaptiveScores(): void
    {
        $intelligence = new Intelligence();
        $intelligence->recordStrategyFeedback('learned.rank', '1.0', [
            'successful' => true,
            'prediction_accuracy' => 1.0,
            'latency_ms' => 1,
        ], 'incident-routing');
        $intelligence->recordStrategyFeedback('rules.ground', '1.0', [
            'successful' => false,
            'prediction_accuracy' => 0.0,
            'latency_ms' => 100,
        ], 'incident-routing');

        $learned = $this->adapter('learned.rank', StrategyDefinition::MODE_LEARNED, 1);
        $deterministic = $this->adapter('rules.ground', StrategyDefinition::MODE_DETERMINISTIC, 100);
        $request = $this->request([
            'candidates' => [
                ['id' => 'learned.rank', 'version' => '1.0'],
                ['id' => 'rules.ground', 'version' => '1.0'],
            ],
            'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC, StrategyDefinition::MODE_LEARNED],
            'selection_policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
            'context_key' => 'incident-routing',
        ]);

        $result = (new StrategyRouter([$learned, $deterministic], $intelligence))->route(
            $request,
            $this->authorization()
        );

        $this->assertSame('rules.ground', $result->selected_strategy['id']);
        $this->assertSame(0, $learned->executions);
        $this->assertSame(1, $deterministic->executions);
    }

    public function testRouterObservationsBecomeReusableIntelligencePerformanceEvidence(): void
    {
        $intelligence = new Intelligence();
        $adapter = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC, 4);
        $request = $this->request([
            'selection_policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
            'context_key' => 'incident-routing',
        ]);

        $result = (new StrategyRouter([$adapter], $intelligence))->route(
            $request,
            $this->authorization()
        );
        $performance = $intelligence->strategyPerformance('tree.dispatch', '1.0', 'incident-routing');

        $this->assertTrue($result->completed());
        $this->assertSame(1, $performance['observations']);
        $this->assertSame(1, $performance['successes']);
        $this->assertSame(4.0, $performance['average_latency_ms']);
        $this->assertGreaterThan(0.0, $performance['efficiency']);
    }

    public function testAdaptivePolicyWithoutAdvisorRetainsDeclaredOrderAndReportsFallback(): void
    {
        $first = $this->adapter('first.tree', StrategyDefinition::MODE_DETERMINISTIC, 10);
        $second = $this->adapter('second.tree', StrategyDefinition::MODE_DETERMINISTIC, 1);

        $result = (new StrategyRouter([$first, $second]))->route(
            $this->request([
                'candidates' => [
                    ['id' => 'first.tree', 'version' => '1.0'],
                    ['id' => 'second.tree', 'version' => '1.0'],
                ],
                'selection_policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
            ]),
            $this->authorization()
        );

        $this->assertSame('first.tree', $result->selected_strategy['id']);
        $this->assertSame('advisor_unavailable', $result->selection_advice['diagnostics'][0]['code']);
    }

    public function testAdaptiveScoreCannotBypassEligibility(): void
    {
        $intelligence = new Intelligence();
        $intelligence->recordStrategyFeedback('blocked.tree', '1.0', [
            'successful' => true,
            'prediction_accuracy' => 1.0,
            'latency_ms' => 1,
        ], 'incident-routing');
        $intelligence->recordStrategyFeedback('eligible.tree', '1.0', [
            'successful' => false,
            'prediction_accuracy' => 0.0,
            'latency_ms' => 100,
        ], 'incident-routing');

        $blocked = $this->adapter('blocked.tree', StrategyDefinition::MODE_DETERMINISTIC, 1, false);
        $eligible = $this->adapter('eligible.tree', StrategyDefinition::MODE_DETERMINISTIC, 100);
        $result = (new StrategyRouter([$blocked, $eligible], $intelligence))->route(
            $this->request([
                'candidates' => [
                    ['id' => 'blocked.tree', 'version' => '1.0'],
                    ['id' => 'eligible.tree', 'version' => '1.0'],
                ],
                'selection_policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
            ]),
            $this->authorization()
        );

        $this->assertSame('eligible.tree', $result->selected_strategy['id']);
        $this->assertSame(0, $blocked->executions);
        $this->assertSame('fixture_ineligible', $result->attempts[0]['code']);
    }

    public function testUnknownSelectionPolicyFailsBeforeAdvisorOrExecution(): void
    {
        $intelligence = new Intelligence();
        $adapter = $this->adapter('tree.dispatch', StrategyDefinition::MODE_DETERMINISTIC, 1);

        $result = (new StrategyRouter([$adapter], $intelligence))->route(
            $this->request(['selection_policy' => 'freelance']),
            $this->authorization()
        );

        $this->assertSame(StrategyRouter::CODE_INVALID_REQUEST, $result->code);
        $this->assertSame(0, $adapter->executions);
        $this->assertSame(0, $intelligence->strategyPerformance(
            'tree.dispatch',
            '1.0',
            'incident-routing'
        )['observations']);
    }

    private function adapter(
        string $id,
        string $mode,
        int $latencyMs,
        bool $eligible = true
    ): IntelligenceAdvisorTestAdapter
    {
        return new IntelligenceAdvisorTestAdapter($id, $mode, $latencyMs, $eligible);
    }

    private function request(array $overrides = []): StrategyRouteRequest
    {
        return new StrategyRouteRequest(array_replace_recursive([
            'id' => 'adaptive-route-1',
            'subject_id' => 'agent-policy',
            'capability_id' => 'policy.route',
            'capability_version' => '1.0',
            'candidates' => [['id' => 'tree.dispatch', 'version' => '1.0']],
            'input' => ['severity' => 'critical'],
            'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC],
            'deterministic_preferred' => true,
            'selection_policy' => StrategyRouteRequest::SELECTION_DECLARED,
            'context_key' => 'incident-routing',
        ], $overrides));
    }

    private function authorization(): AutonomyDecision
    {
        return new AutonomyDecision([
            'allowed' => true,
            'code' => AutonomyDecision::CODE_ALLOWED,
            'subject_id' => 'agent-policy',
            'capability_id' => 'policy.route',
            'capability_version' => '1.0',
        ]);
    }
}

class IntelligenceAdvisorTestAdapter implements IStrategyRouteAdapter
{
    public int $executions = 0;

    public function __construct(
        private string $id,
        private string $mode,
        private int $latencyMs,
        private bool $eligible
    ) {
    }

    public function definition(): StrategyDefinition
    {
        return new StrategyDefinition([
            'id' => $this->id,
            'version' => '1.0',
            'capability_id' => 'policy.route',
            'capability_version' => '1.0',
            'family' => $this->id,
            'mode' => $this->mode,
            'side_effect_free' => true,
            'availability' => StrategyDefinition::AVAILABILITY_AVAILABLE,
        ]);
    }

    public function eligibility(StrategyRouteRequest $request): StrategyEligibility
    {
        return new StrategyEligibility([
            'eligible' => $this->eligible,
            'code' => $this->eligible ? StrategyEligibility::CODE_ELIGIBLE : 'fixture_ineligible',
        ]);
    }

    public function estimate(StrategyRouteRequest $request): StrategyUsage
    {
        return new StrategyUsage(['latency_ms' => $this->latencyMs, 'invocations' => 1]);
    }

    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        $this->executions++;

        return new StrategyAdapterResult([
            'status' => StrategyAdapterResult::STATUS_COMPLETED,
            'code' => StrategyAdapterResult::CODE_COMPLETED,
            'output' => ['strategy' => $this->id],
            'usage' => ['latency_ms' => $this->latencyMs, 'invocations' => 1],
            'confidence' => 0.9,
        ]);
    }
}
