<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use BlueFission\Automata\Goal\ComparisonOperator;
use BlueFission\Automata\Goal\Condition;
use BlueFission\Automata\Goal\Initiative;
use BlueFission\Automata\LLM\Agent\State\AgentState;
use BlueFission\Automata\LLM\Agent\State\CognitiveController;

$state = new AgentState();
$state
    ->allowInState(AgentState::STATE_ACTING, AgentState::ACTION_USE_TOOL)
    ->denyInState(AgentState::STATE_ACTING, AgentState::ACTION_SPEAK);

$survival = new Initiative([
    'name' => 'survival',
    'weight' => 2,
]);

$survival->addCondition(new Condition([
    'name' => 'food',
    'path' => 'inventory.food',
    'operator' => ComparisonOperator::AT_LEAST,
    'expected' => 1,
    'priority' => 3,
    'action' => 'gather-food',
]));

$state->addGoal($survival);
$state->write(AgentState::OBSERVATIONS, 'inventory', [
    'food' => 0,
]);

$controller = new CognitiveController();
$decision = $controller->decide($state, null, [
    'inventory' => [
        'food' => 0,
    ],
]);

$payload = $decision->decision();
print_r([
    'selected' => $payload['selected'] ?? null,
    'options' => $payload['options'] ?? [],
]);
