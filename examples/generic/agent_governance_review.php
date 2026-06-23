<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\LLM\Agent\Governance\HumanReviewGate;
use BlueFission\Automata\LLM\Agent\Governance\TaskCallMonitor;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;

$trace = new TaskTrace('incident-review-42');

$reviewGate = new HumanReviewGate(function (array $call): GovernanceDecision {
    if (($call['request']['method'] ?? '') === 'records/delete') {
        return GovernanceDecision::denied('Deletion requires a separate operator workflow.');
    }

    if (($call['request']['method'] ?? '') === 'tickets/update') {
        return GovernanceDecision::steered([
            'params' => [
                'status' => 'needs-human-review',
            ],
        ], 'Keep the ticket in review until an operator signs off.');
    }

    return GovernanceDecision::approved('Read-only call approved.');
});

$monitor = new TaskCallMonitor($trace, [
    'review_kinds' => [
        TaskTraceSpan::KIND_MCP,
        TaskTraceSpan::KIND_RPC,
        TaskTraceSpan::KIND_API,
    ],
], $reviewGate);

$read = $monitor->observe(
    TaskTraceSpan::KIND_MCP,
    'memory.resources/read',
    fn (array $request): array => ['resource' => $request['params']['uri'], 'contents' => 'summary'],
    [
        'method' => 'resources/read',
        'params' => [
            'uri' => 'memory://incident/42',
        ],
    ]
);

$steered = $monitor->observe(
    TaskTraceSpan::KIND_RPC,
    'tickets.update',
    fn (array $request): array => ['updated' => $request['params']],
    [
        'method' => 'tickets/update',
        'params' => [
            'status' => 'resolved',
        ],
    ]
);

$blocked = $monitor->observe(
    TaskTraceSpan::KIND_API,
    'records.delete',
    fn (): array => ['deleted' => true],
    [
        'method' => 'records/delete',
        'params' => [
            'id' => 42,
        ],
    ]
);

echo "read: " . $read['status'] . PHP_EOL;
echo "steered: " . $steered['decision']['status'] . ' -> ' . $steered['payload']['updated']['status'] . PHP_EOL;
echo "blocked: " . $blocked['error']['code'] . PHP_EOL;
echo "spans: " . count($trace->toArray()['spans']) . PHP_EOL;
