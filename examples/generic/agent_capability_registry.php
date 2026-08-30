<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\LLM\Agent\Capability\AutonomyPacket;
use BlueFission\Automata\LLM\Agent\Capability\CapabilityDefinition;
use BlueFission\Automata\LLM\Agent\Capability\CapabilityRegistry;

$registry = new CapabilityRegistry([
    new CapabilityDefinition([
        'id' => 'evidence.read',
        'version' => '1.0',
        'owner' => 'automata',
        'description' => 'Read bounded evidence references.',
        'inputs' => ['query'],
        'outputs' => ['evidence_references'],
        'constraints' => ['locator_only' => true],
        'risk' => ['level' => 'low'],
        'availability' => CapabilityDefinition::AVAILABILITY_AVAILABLE,
    ]),
]);

$packet = new AutonomyPacket([
    'id' => 'autonomy-example-1',
    'subject_id' => 'research-agent',
    'requested_capabilities' => [
        ['id' => 'evidence.read', 'version' => '1.0'],
    ],
    'grants' => [[
        'capability_id' => 'evidence.read',
        'capability_version' => '1.0',
        'subject_id' => 'research-agent',
        'granted' => true,
        'limits' => ['max_calls' => 3],
        'evidence' => [['source' => 'operator-approval']],
    ]],
    'approval_state' => AutonomyPacket::APPROVAL_APPROVED,
    'approval_reference' => 'review-42',
    'trace_id' => 'trace-capability-example',
]);

$result = [
    'allowed' => $packet->authorize('evidence.read', '1.0', $registry)->toArray(),
    'unknown_version' => $packet->authorize('evidence.read', '2.0', $registry)->toArray(),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
