<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestratedAgent;
use BlueFission\Automata\LLM\Agent\Orchestration\Orchestrator;

$villagerMind = new OrchestratedAgent('villager_inner_mind', [
    'pattern' => OrchestrationConfig::HIERARCHICAL,
    'supervisor' => fn (): array => [
        'output' => ['workers' => ['lead', 'memory_counselor', 'policy_counselor']],
    ],
    'workers' => [
        'lead' => fn (array $context): array => [
            'output' => ['proposal' => 'respond_to_' . $context['perceptions']['need']],
        ],
        'memory_counselor' => fn (array $context): array => [
            'output' => ['memory' => $context['shared_context']['memory']],
        ],
        'policy_counselor' => fn (array $context): array => [
            'output' => ['constraint' => $context['shared_context']['rule']],
        ],
    ],
], [
    'include' => ['perceptions', 'shared_context'],
]);

$society = new Orchestrator([
    'pattern' => OrchestrationConfig::PIANO,
    'supervisor' => fn (): array => ['output' => ['goal' => 'coordinate village']],
    'workers' => [
        'villager' => $villagerMind,
        'broadcast' => fn (array $context): array => [
            'output' => ['broadcast' => $context['controller_decision']['goal']],
        ],
    ],
]);

$result = $society->run([
    'perceptions' => ['need' => 'food'],
    'shared_context' => ['memory' => 'hungry', 'rule' => 'share tools'],
    'private_plan' => 'not available to the inner mind',
])->toArray();

print_r([
    'villager' => $result['output']['villager'] ?? [],
    'broadcast' => $result['output']['broadcast'] ?? null,
    'private_leaked' => Arr::hasKey($result['output']['villager'] ?? [], 'private_plan'),
]);
