<?php

namespace BlueFission\Automata\LLM\Agent\Capability;

use BlueFission\DataTypes;
use DateTimeImmutable;
use Throwable;

class AutonomyGrant extends CapabilityValue
{
    protected $_data = [
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

    protected $_types = [
        'capability_id' => DataTypes::STRING,
        'capability_version' => DataTypes::STRING,
        'subject_id' => DataTypes::STRING,
        'granted' => DataTypes::BOOLEAN,
        'transferable' => DataTypes::BOOLEAN,
        'constraints' => DataTypes::ARRAY,
        'limits' => DataTypes::ARRAY,
        'expires_at' => DataTypes::GENERIC,
        'revoked' => DataTypes::BOOLEAN,
        'revocation_reason' => DataTypes::STRING,
        'evidence' => DataTypes::ARRAY,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function expired(?DateTimeImmutable $now = null): bool
    {
        $expiresAt = $this->expires_at;
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
        return $this->granted
            && !$this->revoked
            && !$this->expired($now)
            && $this->capability_id === $capabilityId
            && $this->capability_version === $capabilityVersion
            && $this->subject_id === $subjectId;
    }
}
