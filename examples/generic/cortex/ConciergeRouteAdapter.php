<?php

namespace CortexExample;

use BlueFission\Automata\Learning\ModelCandidate;
use BlueFission\Automata\Strategy\Routing\IStrategyRouteAdapter;
use BlueFission\Automata\Strategy\Routing\StrategyAdapterResult;
use BlueFission\Automata\Strategy\Routing\StrategyDefinition;
use BlueFission\Automata\Strategy\Routing\StrategyEligibility;
use BlueFission\Automata\Strategy\Routing\StrategyRouteRequest;
use BlueFission\Automata\Strategy\Routing\StrategyUsage;

/** Domain assembly: expose an already trained concierge classifier to the router. */
final class ConciergeRouteAdapter implements IStrategyRouteAdapter
{
    public int $executions = 0;
    public bool $eligible = true;

    public function __construct(private readonly ModelCandidate $model, private readonly string $mode) {}

    public function definition(): StrategyDefinition
    {
        return new StrategyDefinition([...$this->model->identity(),
            'capability_id' => 'concierge.intent', 'capability_version' => '1',
            'family' => 'classification', 'mode' => $this->mode, 'side_effect_free' => true,
            'availability' => StrategyDefinition::AVAILABILITY_AVAILABLE]);
    }

    public function eligibility(StrategyRouteRequest $request): StrategyEligibility
    {
        return new StrategyEligibility(['eligible' => $this->eligible,
            'code' => $this->eligible ? StrategyEligibility::CODE_ELIGIBLE : 'fixture_disabled']);
    }

    public function estimate(StrategyRouteRequest $request): StrategyUsage
    {
        return new StrategyUsage(['invocations' => 1]);
    }

    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        ++$this->executions;
        return new StrategyAdapterResult(['status' => StrategyAdapterResult::STATUS_COMPLETED,
            'code' => StrategyAdapterResult::CODE_COMPLETED,
            'output' => $this->model->strategy()->predict($request->input),
            'usage' => ['invocations' => 1]]);
    }
}
