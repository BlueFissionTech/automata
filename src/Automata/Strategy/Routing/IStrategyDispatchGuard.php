<?php

namespace BlueFission\Automata\Strategy\Routing;

/** Synchronous veto only; an allow never replaces ordinary route authority. */
interface IStrategyDispatchGuard
{
    public function check(StrategyDispatchContext $context): StrategyDispatchDecision;
}
