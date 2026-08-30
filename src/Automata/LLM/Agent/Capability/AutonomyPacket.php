<?php

namespace BlueFission\Automata\LLM\Agent\Capability;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use DateTimeImmutable;
use Throwable;

class AutonomyPacket extends CapabilityValue
{
    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_DENIED = 'denied';

    public function id(): string
    {
        return (string)$this->field('id');
    }

    public function subjectId(): string
    {
        return (string)$this->field('subject_id');
    }

    public function grants(): array
    {
        return Arr::make($this->field('grants') ?? [])
            ->map(static fn (mixed $grant): AutonomyGrant => $grant instanceof AutonomyGrant
                ? $grant
                : new AutonomyGrant(Arr::make($grant)->toArray()))
            ->toArray();
    }

    public function revoked(): bool
    {
        return (bool)$this->field('revoked');
    }

    public function expired(?DateTimeImmutable $now = null): bool
    {
        $expiresAt = $this->field('expires_at');
        if (!$expiresAt) {
            return false;
        }

        try {
            return new DateTimeImmutable((string)$expiresAt) <= ($now ?? new DateTimeImmutable());
        } catch (Throwable) {
            return true;
        }
    }

    public function authorize(
        string $capabilityId,
        string $capabilityVersion,
        CapabilityRegistry $registry,
        ?DateTimeImmutable $now = null
    ): AutonomyDecision {
        $definition = $registry->definition($capabilityId, $capabilityVersion);
        if (!$definition) {
            return $this->decision(false, AutonomyDecision::CODE_UNKNOWN_CAPABILITY, $capabilityId, $capabilityVersion);
        }

        if (!$definition->available()) {
            return $this->decision(false, AutonomyDecision::CODE_CAPABILITY_UNAVAILABLE, $capabilityId, $capabilityVersion, $definition);
        }

        if ($this->revoked()) {
            return $this->decision(false, AutonomyDecision::CODE_PACKET_REVOKED, $capabilityId, $capabilityVersion, $definition);
        }

        if ($this->expired($now)) {
            return $this->decision(false, AutonomyDecision::CODE_PACKET_EXPIRED, $capabilityId, $capabilityVersion, $definition);
        }

        if ($this->field('approval_state') !== self::APPROVAL_APPROVED) {
            return $this->decision(false, AutonomyDecision::CODE_APPROVAL_REQUIRED, $capabilityId, $capabilityVersion, $definition);
        }

        if (!$this->requested($capabilityId, $capabilityVersion)) {
            return $this->decision(false, AutonomyDecision::CODE_CAPABILITY_NOT_REQUESTED, $capabilityId, $capabilityVersion, $definition);
        }

        $grant = $this->matchingGrant($capabilityId, $capabilityVersion);
        if (!$grant) {
            return $this->decision(false, AutonomyDecision::CODE_CAPABILITY_NOT_GRANTED, $capabilityId, $capabilityVersion, $definition);
        }

        if ($grant->subjectId() !== $this->subjectId()) {
            return $this->decision(false, AutonomyDecision::CODE_SUBJECT_MISMATCH, $capabilityId, $capabilityVersion, $definition, $grant);
        }

        if ($grant->revoked()) {
            return $this->decision(false, AutonomyDecision::CODE_GRANT_REVOKED, $capabilityId, $capabilityVersion, $definition, $grant);
        }

        if ($grant->expired($now)) {
            return $this->decision(false, AutonomyDecision::CODE_GRANT_EXPIRED, $capabilityId, $capabilityVersion, $definition, $grant);
        }

        if (!$grant->permits($capabilityId, $capabilityVersion, $this->subjectId(), $now)) {
            return $this->decision(false, AutonomyDecision::CODE_CAPABILITY_NOT_GRANTED, $capabilityId, $capabilityVersion, $definition, $grant);
        }

        return $this->decision(true, AutonomyDecision::CODE_ALLOWED, $capabilityId, $capabilityVersion, $definition, $grant);
    }

    protected function requested(string $capabilityId, string $capabilityVersion): bool
    {
        foreach (Arr::make($this->field('requested_capabilities') ?? [])->toArray() as $requested) {
            $requested = Arr::make($requested)->toArray();
            if (($requested['id'] ?? '') === $capabilityId && ($requested['version'] ?? '') === $capabilityVersion) {
                return true;
            }
        }

        return false;
    }

    protected function matchingGrant(string $capabilityId, string $capabilityVersion): ?AutonomyGrant
    {
        foreach ($this->grants() as $grant) {
            if ($grant->capabilityId() === $capabilityId && $grant->capabilityVersion() === $capabilityVersion) {
                return $grant;
            }
        }

        return null;
    }

    protected function decision(
        bool $allowed,
        string $code,
        string $capabilityId,
        string $capabilityVersion,
        ?CapabilityDefinition $definition = null,
        ?AutonomyGrant $grant = null
    ): AutonomyDecision {
        $packet = $this->toArray();
        $limits = ToolDefinition::mergeConfig(
            Arr::make($packet['limits'] ?? [])->toArray(),
            $grant?->limits() ?? []
        );
        $constraints = ToolDefinition::mergeConfig(
            $definition?->constraints() ?? [],
            ToolDefinition::mergeConfig(
                Arr::make($packet['constraints'] ?? [])->toArray(),
                $grant?->constraints() ?? []
            )
        );
        $evidence = Arr::make([]);
        foreach ([
            $definition?->evidence() ?? [],
            $grant?->evidence() ?? [],
            Arr::make($packet['evidence'] ?? [])->toArray(),
        ] as $entries) {
            foreach ($entries as $entry) {
                $evidence->push($entry);
            }
        }

        return new AutonomyDecision([
            'allowed' => $allowed,
            'code' => $code,
            'reason' => $code,
            'packet_id' => $this->id(),
            'subject_id' => $this->subjectId(),
            'capability_id' => $capabilityId,
            'capability_version' => $capabilityVersion,
            'limits' => $limits,
            'constraints' => $constraints,
            'risk' => $definition?->field('risk') ?? [],
            'evidence' => $evidence->toArray(),
            'correlation_id' => $packet['correlation_id'] ?? '',
            'causation_id' => $packet['causation_id'] ?? '',
            'trace_id' => $packet['trace_id'] ?? '',
        ]);
    }

    protected function defaults(): array
    {
        return [
            'id' => '',
            'subject_id' => '',
            'requested_capabilities' => [],
            'grants' => [],
            'constraints' => [],
            'limits' => [],
            'approval_state' => self::APPROVAL_PENDING,
            'approval_reference' => '',
            'expires_at' => null,
            'revoked' => false,
            'revocation_reason' => '',
            'evidence' => [],
            'correlation_id' => '',
            'causation_id' => '',
            'trace_id' => '',
            'metadata' => [],
        ];
    }
}
