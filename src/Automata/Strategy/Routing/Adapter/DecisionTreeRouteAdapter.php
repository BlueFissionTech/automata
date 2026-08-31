<?php

namespace BlueFission\Automata\Strategy\Routing\Adapter;

use BlueFission\Arr;
use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\DepthFirstTraceMethod;
use BlueFission\Automata\DecisionTree\INode;
use BlueFission\Automata\Strategy\Routing\IStrategyRouteAdapter;
use BlueFission\Automata\Strategy\Routing\StrategyAdapterResult;
use BlueFission\Automata\Strategy\Routing\StrategyDefinition;
use BlueFission\Automata\Strategy\Routing\StrategyEligibility;
use BlueFission\Automata\Strategy\Routing\StrategyRouteRequest;
use BlueFission\Automata\Strategy\Routing\StrategyUsage;

class DecisionTreeRouteAdapter implements IStrategyRouteAdapter
{
    public function __construct(
        protected DecisionTree $tree,
        protected DepthFirstTraceMethod $method,
        protected StrategyDefinition $strategyDefinition,
        protected StrategyEligibility $strategyEligibility,
        protected StrategyUsage $estimatedUsage
    ) {
    }

    public function definition(): StrategyDefinition
    {
        return $this->strategyDefinition;
    }

    public function eligibility(StrategyRouteRequest $request): StrategyEligibility
    {
        return $this->strategyEligibility;
    }

    public function estimate(StrategyRouteRequest $request): StrategyUsage
    {
        return $this->estimatedUsage;
    }

    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        $this->method->setState($request->input);
        $decision = $this->tree->decide($this->method);
        $trace = Arr::make($this->method->getTrace())
            ->map(static fn (INode $node): array => $node->getValue())
            ->toArray();

        return new StrategyAdapterResult([
            'status' => StrategyAdapterResult::STATUS_COMPLETED,
            'code' => StrategyAdapterResult::CODE_COMPLETED,
            'output' => [
                'decision' => $decision,
                'trace' => $trace,
            ],
            'usage' => $this->estimatedUsage->toArray(),
            'evidence' => $this->strategyEligibility->toArray()['evidence'],
        ]);
    }
}
