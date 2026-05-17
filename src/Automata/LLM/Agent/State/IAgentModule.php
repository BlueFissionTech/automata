<?php

namespace BlueFission\Automata\LLM\Agent\State;

interface IAgentModule
{
    public function name(): string;
    public function process(AgentState $state, array $context = []): AgentModuleResult;
}
