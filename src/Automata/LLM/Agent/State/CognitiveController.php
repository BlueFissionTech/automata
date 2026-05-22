<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Automata\Goal\IGoalManager;

class CognitiveController implements IStateController
{
    use ControlsAgentState;

    /**
     * Create the default state controller with injectable priorities and goals.
     */
    public function __construct(array $priorities = [], int $limitPerChannel = 5, ?IGoalManager $goalManager = null, int $optionLimit = 5)
    {
        $this->initializeStateController($priorities, $limitPerChannel, $goalManager, $optionLimit);
    }
}
