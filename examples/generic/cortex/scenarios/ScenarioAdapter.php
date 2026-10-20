<?php

namespace CortexExample\Scenarios;

use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouteRequest, StrategyUsage};
use Closure;

/**
 * Bind a synthetic, side-effect-free policy to the real strategy router.
 *
 * This adapter returns proposals only. World mutation, authorization and the
 * independent outcome oracle belong to the scenario host, outside the policy.
 */
final class ScenarioAdapter implements IStrategyRouteAdapter
{
    private Closure $policy;
    public int $executions = 0;

    /** Exact identities make attribution and workflow bindings reproducible. */
    public function __construct(
        private readonly string $id,
        callable $policy,
        private readonly string $capability = 'scenario.propose',
        private readonly string $mode = StrategyDefinition::MODE_DETERMINISTIC
    ) {
        $this->policy = Closure::fromCallable($policy);
    }

    /** A fixed policy is deterministic even when its selection is adaptive. */
    public function definition(): StrategyDefinition
    {
        return new StrategyDefinition([
            'id' => $this->id, 'version' => '1',
            'capability_id' => $this->capability, 'capability_version' => '1',
            'mode' => $this->mode,
            'side_effect_free' => true,
            'availability' => StrategyDefinition::AVAILABILITY_AVAILABLE,
        ]);
    }

    /** Host authorization is still required independently of eligibility. */
    public function eligibility(StrategyRouteRequest $request): StrategyEligibility
    {
        return new StrategyEligibility(['eligible' => true]);
    }

    /** Unmeasured monetary cost stays unknown rather than being declared free. */
    public function estimate(StrategyRouteRequest $request): StrategyUsage
    {
        return new StrategyUsage(['invocations' => 1, 'cost' => null]);
    }

    /** Exceptions propagate to the router's normal uncertainty handling. */
    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        ++$this->executions;
        return new StrategyAdapterResult([
            'status' => StrategyAdapterResult::STATUS_COMPLETED,
            'code' => StrategyAdapterResult::CODE_COMPLETED,
            'output' => ($this->policy)($request->input),
            'usage' => ['invocations' => 1, 'cost' => null],
        ]);
    }
}
