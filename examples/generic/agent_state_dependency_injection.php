<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\Goal\ComparisonOperator;
use BlueFission\Automata\Goal\Condition;
use BlueFission\Automata\Goal\IGoalManager;
use BlueFission\Automata\Goal\Initiative;
use BlueFission\Automata\Goal\ManagesGoals;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\State\AgentModuleResult;
use BlueFission\Automata\LLM\Agent\State\AgentState;
use BlueFission\Automata\LLM\Agent\State\ControlsAgentState;
use BlueFission\Automata\LLM\Agent\State\IStateController;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;

class ExampleClient implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        return new Reply();
    }

    public function complete($input, $config = []): Reply
    {
        return new Reply();
    }

    public function respond($input, $config = []): Reply
    {
        return new Reply();
    }
}

class ExampleGoalManager implements IGoalManager
{
    use ManagesGoals;

    /**
     * Use the shared goal behavior while keeping constructor policy local.
     */
    public function __construct(array $goals = [])
    {
        $this->initializeGoalManager($goals, 8, 8);
    }
}

class ExampleStateController implements IStateController
{
    use ControlsAgentState;

    /**
     * Use the shared controller pipeline with a narrower PIANO bottleneck.
     */
    public function __construct(IGoalManager $goalManager)
    {
        $this->initializeStateController([
            AgentState::GOALS,
            AgentState::OBSERVATIONS,
        ], 2, $goalManager, 2);
    }

    /**
     * Override the public decision hook when the application needs extra policy.
     */
    public function decide(AgentState $state, mixed $decision = null, array $context = []): AgentModuleResult
    {
        $context['session'] = ['scope' => 'hydration-only'];

        return $this->stateDecision($state, $decision, $context);
    }
}

$hydration = new Initiative([
    'name' => 'hydration',
    'weight' => 2,
]);
$hydration->addCondition(new Condition([
    'name' => 'water',
    'path' => 'inventory.water',
    'operator' => ComparisonOperator::AT_LEAST,
    'expected' => 1,
    'priority' => 4,
    'action' => 'gather-water',
]));

$goals = new ExampleGoalManager([$hydration]);
$state = new AgentState([], $goals);
$agent = new Agent(new ExampleClient());
$agent->useAgentState($state);
$agent->setCognitiveController(new ExampleStateController($goals));

$decision = $agent->cognitiveDecision(null, [
    'inventory' => [
        'water' => 0,
    ],
])->decision();

print_r([
    'selected' => $decision['selected'] ?? null,
    'options' => $decision['options'] ?? [],
]);
