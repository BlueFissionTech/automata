<?php

namespace BlueFission\Automata\Strategy\Routing;

interface IStrategyRouteAdvisor
{
    /**
     * Rank only the exact candidates already declared by the request.
     *
     * @param array<int, array<string, mixed>> $definitions
     */
    public function advise(StrategyRouteRequest $request, array $definitions): StrategyRouteAdvice;

    /**
     * Observe a completed route without changing its authority or outcome.
     */
    public function observe(StrategyRouteRequest $request, StrategyRouteResult $result): void;
}
