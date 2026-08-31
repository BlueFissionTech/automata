<?php

namespace BlueFission\Automata\LLM\Agent\Capability;

use BlueFission\DataTypes;

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

    protected $_data = [
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

    protected $_types = [
        'allowed' => DataTypes::BOOLEAN,
        'code' => DataTypes::STRING,
        'reason' => DataTypes::STRING,
        'packet_id' => DataTypes::STRING,
        'subject_id' => DataTypes::STRING,
        'capability_id' => DataTypes::STRING,
        'capability_version' => DataTypes::STRING,
        'limits' => DataTypes::ARRAY,
        'constraints' => DataTypes::ARRAY,
        'risk' => DataTypes::ARRAY,
        'evidence' => DataTypes::ARRAY,
        'correlation_id' => DataTypes::STRING,
        'causation_id' => DataTypes::STRING,
        'trace_id' => DataTypes::STRING,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;
}
