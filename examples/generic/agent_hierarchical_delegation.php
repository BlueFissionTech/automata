<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\LLM\Agent\Delegation\AgentProfile;
use BlueFission\Automata\LLM\Agent\Delegation\CapabilityGrant;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationRequest;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationResult;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationStatus;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationWorker;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\Orchestrator;

$grant = static fn (string $target, string $capability): CapabilityGrant => new CapabilityGrant([
    'capability' => $capability,
    'source_agent_id' => 'coordinator',
    'target_agent_id' => $target,
    'granted' => true,
]);

$researchRequest = new DelegationRequest([
    'id' => 'research-1',
    'source_agent_id' => 'coordinator',
    'target_agent_id' => 'research',
    'required_capabilities' => ['evidence.read'],
    'capability_grants' => [$grant('research', 'evidence.read')],
    'trace_id' => 'trace-qualification-1',
]);
$reviewRequest = new DelegationRequest([
    'id' => 'review-1',
    'source_agent_id' => 'coordinator',
    'target_agent_id' => 'review',
    'required_capabilities' => ['decision.review'],
    'capability_grants' => [
        $grant('review', 'decision.review'),
        $grant('review', 'peer.results.read'),
    ],
    'peer_agent_ids' => ['research'],
    'allow_peer_results' => true,
    'trace_id' => 'trace-qualification-1',
]);

$research = new DelegationWorker(
    new AgentProfile(['id' => 'research', 'capabilities' => ['evidence.read']]),
    static fn (DelegationRequest $request): DelegationResult => DelegationResult::fromRequest(
        $request,
        'research',
        DelegationStatus::COMPLETED,
        ['company' => 'Example Co', 'verified' => true],
        [],
        [['source' => 'crm-record']]
    )
);
$review = new DelegationWorker(
    new AgentProfile(['id' => 'review', 'capabilities' => ['decision.review', 'peer.results.read']]),
    static fn (DelegationRequest $request, array $peers): DelegationResult => DelegationResult::fromRequest(
        $request,
        'review',
        DelegationStatus::COMPLETED,
        ['decision' => ($peers[0]['output']['verified'] ?? false) ? 'qualified' : 'manual_review']
    )
);

$result = (new Orchestrator([
    'pattern' => OrchestrationConfig::HIERARCHICAL,
    'supervisor' => static fn (): array => ['output' => ['workers' => ['research', 'review']]],
    'workers' => ['research' => $research, 'review' => $review],
]))->run([
    'delegations' => ['research' => $researchRequest, 'review' => $reviewRequest],
])->toArray();

print_r($result);
