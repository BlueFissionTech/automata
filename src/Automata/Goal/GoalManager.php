<?php

namespace BlueFission\Automata\Goal;

class GoalManager implements IGoalManager
{
    use ManagesGoals;

    public const DEFAULT_ACTION = IGoalManager::DEFAULT_ACTION;
    public const DEFAULT_LIMIT = IGoalManager::DEFAULT_LIMIT;

    /**
     * Create the default injectable goal manager.
     */
    public function __construct(array $goals = [], int $maxGoals = 20, int $maxAssociations = 20)
    {
        $this->initializeGoalManager($goals, $maxGoals, $maxAssociations);
    }
}
