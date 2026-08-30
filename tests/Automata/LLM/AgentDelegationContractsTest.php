<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent\Delegation\AgentProfile;
use BlueFission\Automata\LLM\Agent\Delegation\CapabilityGrant;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationRequest;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationResult;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationStatus;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationWorker;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\Orchestrator;
use PHPUnit\Framework\TestCase;

class AgentDelegationContractsTest extends TestCase
{
    public function testHierarchicalDelegationSupportsScopedPeerHandoff(): void
    {
        $researchRequest = $this->request('research', ['evidence.read']);
        $reviewRequest = $this->request('review', ['decision.review'], [
            $this->grant('review', 'decision.review'),
            $this->grant('review', 'peer.results.read'),
        ], ['research']);

        $research = new DelegationWorker(
            new AgentProfile(['id' => 'research', 'capabilities' => ['evidence.read']]),
            static fn (DelegationRequest $request): DelegationResult => DelegationResult::fromRequest(
                $request,
                'research',
                DelegationStatus::COMPLETED,
                ['fact' => 'verified'],
                [],
                [['source' => 'record-42']]
            )
        );
        $review = new DelegationWorker(
            new AgentProfile(['id' => 'review', 'capabilities' => ['decision.review', 'peer.results.read']]),
            static function (DelegationRequest $request, array $peerResults): DelegationResult {
                return DelegationResult::fromRequest(
                    $request,
                    'review',
                    DelegationStatus::COMPLETED,
                    ['reviewed' => $peerResults[0]['output']['fact'] ?? null]
                );
            }
        );

        $result = (new Orchestrator([
            'pattern' => OrchestrationConfig::HIERARCHICAL,
            'supervisor' => static fn (): array => ['output' => ['workers' => ['research', 'review']]],
            'workers' => ['research' => $research, 'review' => $review],
        ]))->run([
            'delegations' => ['research' => $researchRequest, 'review' => $reviewRequest],
        ])->toArray();

        $this->assertSame(DelegationStatus::COMPLETED, $result['status']);
        $this->assertSame('verified', $result['output']['fact']);
        $this->assertSame('verified', $result['output']['reviewed']);
        $this->assertSame(
            [['source' => 'record-42']],
            $result['worker_results'][1]['metadata']['delegation']['evidence']
        );
        $this->assertSame('trace-review', $result['worker_results'][2]['metadata']['delegation']['trace_id']);
    }

    public function testWorkerRejectsCapabilitiesThatWereNotGranted(): void
    {
        $invoked = false;
        $worker = new DelegationWorker(
            new AgentProfile(['id' => 'research', 'capabilities' => ['evidence.read']]),
            static function () use (&$invoked): array {
                $invoked = true;
                return [];
            }
        );

        $result = $worker([
            'delegation' => $this->request('research', ['evidence.read'], []),
        ]);

        $this->assertFalse($invoked);
        $this->assertSame(DelegationStatus::REJECTED, $result['status']);
        $this->assertSame(
            'capability_not_granted:evidence.read',
            $result['metadata']['delegation']['diagnostics']['reason']
        );
    }

    public function testCancellationAndDeadlineStopExecution(): void
    {
        $worker = new DelegationWorker(
            new AgentProfile(['id' => 'research', 'capabilities' => []]),
            static fn (): array => ['should_not_run' => true]
        );

        $cancelled = $worker(['delegation' => $this->request('research', [], [], [], ['cancelled' => true])]);
        $timedOut = $worker(['delegation' => $this->request('research', [], [], [], [
            'deadline' => '2000-01-01T00:00:00Z',
        ])]);

        $this->assertSame(DelegationStatus::CANCELLED, $cancelled['status']);
        $this->assertSame(DelegationStatus::TIMED_OUT, $timedOut['status']);
    }

    public function testHierarchicalPatternReportsPartialFailure(): void
    {
        $complete = new DelegationWorker(
            new AgentProfile(['id' => 'complete', 'capabilities' => []]),
            static fn (): array => ['answer' => 'usable']
        );
        $failed = new DelegationWorker(
            new AgentProfile(['id' => 'failed', 'capabilities' => []]),
            static function (): void {
                throw new \RuntimeException('specialist unavailable');
            }
        );

        $result = (new Orchestrator([
            'pattern' => OrchestrationConfig::HIERARCHICAL,
            'supervisor' => static fn (): array => ['output' => ['workers' => ['complete', 'failed']]],
            'workers' => ['complete' => $complete, 'failed' => $failed],
        ]))->run([
            'delegations' => [
                'complete' => $this->request('complete'),
                'failed' => $this->request('failed'),
            ],
        ])->toArray();

        $this->assertSame(DelegationStatus::PARTIAL, $result['status']);
        $this->assertSame('usable', $result['output']['answer']);
        $this->assertSame(
            'specialist unavailable',
            $result['worker_results'][2]['metadata']['delegation']['diagnostics']['reason']
        );
    }

    private function request(
        string $target,
        array $requiredCapabilities = [],
        ?array $grants = null,
        array $peers = [],
        array $overrides = []
    ): DelegationRequest {
        $grants ??= array_map(fn (string $capability): CapabilityGrant => $this->grant($target, $capability), $requiredCapabilities);

        return new DelegationRequest(array_merge([
            'id' => 'delegation-' . $target,
            'source_agent_id' => 'coordinator',
            'target_agent_id' => $target,
            'required_capabilities' => $requiredCapabilities,
            'capability_grants' => $grants,
            'peer_agent_ids' => $peers,
            'allow_peer_results' => $peers !== [],
            'correlation_id' => 'correlation-1',
            'causation_id' => 'coordinator-plan',
            'trace_id' => 'trace-' . $target,
        ], $overrides));
    }

    private function grant(string $target, string $capability): CapabilityGrant
    {
        return new CapabilityGrant([
            'capability' => $capability,
            'source_agent_id' => 'coordinator',
            'target_agent_id' => $target,
            'granted' => true,
        ]);
    }
}
