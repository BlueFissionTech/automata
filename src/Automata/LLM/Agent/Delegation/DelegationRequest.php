<?php

namespace BlueFission\Automata\LLM\Agent\Delegation;

use BlueFission\Arr;
use DateTimeImmutable;
use Throwable;

class DelegationRequest extends DelegationValue
{
    public function id(): string
    {
        return (string)$this->field('id');
    }

    public function sourceAgentId(): string
    {
        return (string)$this->field('source_agent_id');
    }

    public function targetAgentId(): string
    {
        return (string)$this->field('target_agent_id');
    }

    public function requiredCapabilities(): array
    {
        return Arr::make($this->field('required_capabilities') ?? [])->toArray();
    }

    public function grants(): array
    {
        return array_map(
            static fn (mixed $grant): CapabilityGrant => $grant instanceof CapabilityGrant
                ? $grant
                : new CapabilityGrant(Arr::make($grant)->toArray()),
            Arr::make($this->field('capability_grants') ?? [])->toArray()
        );
    }

    public function permits(string $capability): bool
    {
        foreach ($this->grants() as $grant) {
            if ($grant->permits($capability, $this->sourceAgentId(), $this->targetAgentId())) {
                return true;
            }
        }

        return false;
    }

    public function isCancelled(): bool
    {
        return (bool)$this->field('cancelled');
    }

    public function isTimedOut(?DateTimeImmutable $now = null): bool
    {
        $deadline = $this->field('deadline');
        if (!$deadline) {
            return false;
        }

        try {
            return new DateTimeImmutable((string)$deadline) <= ($now ?? new DateTimeImmutable());
        } catch (Throwable) {
            return true;
        }
    }

    public function peerAgentIds(): array
    {
        return Arr::make($this->field('peer_agent_ids') ?? [])->toArray();
    }

    public function allowsPeerResults(): bool
    {
        return (bool)$this->field('allow_peer_results') && $this->permits('peer.results.read');
    }

    protected function defaults(): array
    {
        return [
            'id' => '',
            'parent_id' => null,
            'source_agent_id' => '',
            'target_agent_id' => '',
            'task' => [],
            'goal' => [],
            'context' => [],
            'tools' => [],
            'budget' => [],
            'policies' => [],
            'completion_criteria' => [],
            'required_capabilities' => [],
            'capability_grants' => [],
            'peer_agent_ids' => [],
            'allow_peer_results' => false,
            'cancelled' => false,
            'deadline' => null,
            'correlation_id' => '',
            'causation_id' => '',
            'trace_id' => '',
            'metadata' => [],
        ];
    }
}
