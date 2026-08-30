<?php

namespace BlueFission\Automata\LLM\Agent\Capability;

use BlueFission\Arr;
use DateTimeImmutable;
use Throwable;

class AutonomyGrant extends CapabilityValue
{
    public function capabilityId(): string
    {
        return (string)$this->field('capability_id');
    }

    public function capabilityVersion(): string
    {
        return (string)$this->field('capability_version');
    }

    public function subjectId(): string
    {
        return (string)$this->field('subject_id');
    }

    public function granted(): bool
    {
        return (bool)$this->field('granted');
    }

    public function transferable(): bool
    {
        return (bool)$this->field('transferable');
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

    public function permits(
        string $capabilityId,
        string $capabilityVersion,
        string $subjectId,
        ?DateTimeImmutable $now = null
    ): bool {
        return $this->granted()
            && !$this->revoked()
            && !$this->expired($now)
            && $this->capabilityId() === $capabilityId
            && $this->capabilityVersion() === $capabilityVersion
            && $this->subjectId() === $subjectId;
    }

    public function limits(): array
    {
        return Arr::make($this->field('limits') ?? [])->toArray();
    }

    public function constraints(): array
    {
        return Arr::make($this->field('constraints') ?? [])->toArray();
    }

    public function evidence(): array
    {
        return Arr::make($this->field('evidence') ?? [])->toArray();
    }

    protected function defaults(): array
    {
        return [
            'capability_id' => '',
            'capability_version' => '',
            'subject_id' => '',
            'granted' => false,
            'transferable' => false,
            'constraints' => [],
            'limits' => [],
            'expires_at' => null,
            'revoked' => false,
            'revocation_reason' => '',
            'evidence' => [],
            'metadata' => [],
        ];
    }
}
