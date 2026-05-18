<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Automata\Goal\IGoalManager;

interface IStateController
{
    /**
     * Attach an explicit goal manager for controller decisions.
     */
    public function useGoalManager(IGoalManager $goalManager): self;

    /**
     * Build a bottlenecked decision from state, goals, and optional caller input.
     */
    public function decide(AgentState $state, mixed $decision = null, array $context = []): AgentModuleResult;
}
