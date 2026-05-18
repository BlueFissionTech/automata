<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationResult;

interface IOrchestrationPattern
{
    public function name(): string;
    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult;
}
