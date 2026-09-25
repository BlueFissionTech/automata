<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\LLM\Agent\State\AgentModuleLifecycle;
use BlueFission\Automata\LLM\Agent\State\AgentModuleRunRequest;
use BlueFission\Automata\LLM\Agent\State\AgentState;
use BlueFission\Automata\LLM\Agent\State\CallableAgentModule;

$state = new AgentState();
$calls = 0;
$module = new CallableAgentModule('fixture', static function () use (&$calls): array {
    $calls++;

    return [
        'module' => 'fixture',
        'decision' => 'continue',
        'writes' => [[
            'channel' => AgentState::DECISIONS,
            'key' => 'fixture',
            'value' => 'continue',
        ]],
    ];
});
$lifecycle = new AgentModuleLifecycle();

$completed = $lifecycle->run($module, $state, new AgentModuleRunRequest(
    GovernanceDecision::approved('Host fixture approval.'),
    [
        'run_id' => 'module-normal',
        'trace_id' => 'trace-normal',
        'correlation_id' => 'correlation-normal',
        'causation_id' => 'causation-normal',
    ]
));
$denied = $lifecycle->run($module, $state, new AgentModuleRunRequest(
    GovernanceDecision::denied('Scope denied.'),
    ['run_id' => 'module-denied']
));
$unsupported = $lifecycle->run($module, $state, new AgentModuleRunRequest(
    GovernanceDecision::approved(),
    [
        'run_id' => 'module-streaming',
        'requested_features' => ['progressive_output'],
    ]
));

$checks = [
    'normal_result_preserves_lineage' => $completed->status() === 'completed'
        && $completed->lineage()['trace_id'] === 'trace-normal',
    'missing_termination_evidence_remains_unknown' => $completed->execution()['termination']['requested'] === null,
    'denial_prevents_invocation' => $denied->status() === 'denied' && $calls === 1,
    'unsupported_feature_is_explicit' => $unsupported->status() === 'unsupported'
        && $unsupported->diagnostics()[0]['features'] === ['progressive_output'],
    'effects_remain_host_owned' => $completed->execution()['effects']['authorization_owner'] === 'host'
        && $completed->execution()['effects']['idempotency_owner'] === 'host',
];

echo json_encode([
    'experiment' => 'agent-module-lifecycle-v1',
    'passed' => !in_array(false, $checks, true),
    'checks' => $checks,
    'contract' => AgentModuleLifecycle::contract(),
    'normal' => $completed->toArray(),
    'denied' => $denied->toArray(),
    'unsupported' => $unsupported->toArray(),
], JSON_PRETTY_PRINT) . PHP_EOL;
