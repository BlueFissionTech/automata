<?php

namespace BlueFission\Automata\Strategy\Routing;

interface IStrategyRouteAdapter
{
    public function definition(): StrategyDefinition;

    public function eligibility(StrategyRouteRequest $request): StrategyEligibility;

    public function estimate(StrategyRouteRequest $request): StrategyUsage;

    public function execute(StrategyRouteRequest $request): StrategyAdapterResult;
}
