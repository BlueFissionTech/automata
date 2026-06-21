<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\LLM\Agent\Lanes\AgentLane;
use BlueFission\Automata\LLM\Agent\Lanes\LanePressureManager;
use BlueFission\Automata\LLM\Agent\Lanes\LanePressureProfile;
use BlueFission\Automata\LLM\Tools\LanePressure;

$metrics = [
    AgentLane::SEMANTIC => [
        'context_load' => 0.78,
        'ambiguity' => 0.46,
    ],
    AgentLane::OPERATIONAL => [
        'policy_conflict' => 0.2,
        'budget_pressure' => 0.35,
    ],
    AgentLane::EXECUTION => [
        'tool_failures' => 0.12,
        'runtime_load' => 0.4,
    ],
];

$manager = LanePressureManager::standard();
$assessment = $manager->assess($metrics, [
    'task_id' => 'example-lane-pressure',
    'runtime' => 'provider-neutral-agent',
]);

print json_encode($assessment, JSON_PRETTY_PRINT) . PHP_EOL;

$readinessMetrics = LanePressureProfile::longHorizonTask([
    'spec' => true,
    'source_map' => 0.6,
    'durable_memory' => true,
    'runbook' => false,
    'milestones' => 0.5,
    'audit_log' => true,
    'verification' => 0.8,
    'observability' => false,
    'isolated_workspace' => true,
    'repair_loop' => true,
    'rollback_plan' => false,
    'local_governance' => 0.7,
]);

print json_encode($manager->assess($readinessMetrics, [
    'task_id' => 'example-long-horizon-readiness',
]), JSON_PRETTY_PRINT) . PHP_EOL;

$tool = new LanePressure($manager);
print $tool->execute([
    'metrics' => $metrics,
    'context' => ['task_id' => 'example-lane-pressure-tool'],
]) . PHP_EOL;
