<?php

namespace BlueFission\Automata\LLM\Agent\Capability;

use BlueFission\Arr;

class AutonomyDecision extends CapabilityValue
{
    public const CODE_ALLOWED = 'allowed';
    public const CODE_UNKNOWN_CAPABILITY = 'unknown_capability';
    public const CODE_CAPABILITY_UNAVAILABLE = 'capability_unavailable';
    public const CODE_PACKET_REVOKED = 'packet_revoked';
    public const CODE_PACKET_EXPIRED = 'packet_expired';
    public const CODE_APPROVAL_REQUIRED = 'approval_required';
    public const CODE_CAPABILITY_NOT_REQUESTED = 'capability_not_requested';
    public const CODE_CAPABILITY_NOT_GRANTED = 'capability_not_granted';
    public const CODE_SUBJECT_MISMATCH = 'subject_mismatch';
    public const CODE_GRANT_REVOKED = 'grant_revoked';
    public const CODE_GRANT_EXPIRED = 'grant_expired';

    public function allowed(): bool
    {
        return (bool)$this->field('allowed');
    }

    public function code(): string
    {
        return (string)$this->field('code');
    }

    public function subjectId(): string
    {
        return (string)$this->field('subject_id');
    }

    public function capabilityId(): string
    {
        return (string)$this->field('capability_id');
    }

    public function capabilityVersion(): string
    {
        return (string)$this->field('capability_version');
    }

    public function limits(): array
    {
        return Arr::make($this->field('limits') ?? [])->toArray();
    }

    public function evidence(): array
    {
        return Arr::make($this->field('evidence') ?? [])->toArray();
    }

    protected function defaults(): array
    {
        return [
            'allowed' => false,
            'code' => self::CODE_CAPABILITY_NOT_GRANTED,
            'reason' => '',
            'packet_id' => '',
            'subject_id' => '',
            'capability_id' => '',
            'capability_version' => '',
            'limits' => [],
            'constraints' => [],
            'risk' => [],
            'evidence' => [],
            'correlation_id' => '',
            'causation_id' => '',
            'trace_id' => '',
            'metadata' => [],
        ];
    }
}
