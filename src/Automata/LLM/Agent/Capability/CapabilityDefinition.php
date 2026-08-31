<?php

namespace BlueFission\Automata\LLM\Agent\Capability;

use BlueFission\Arr;
use BlueFission\DataTypes;

class CapabilityDefinition extends CapabilityValue
{
    public const AVAILABILITY_AVAILABLE = 'available';
    public const AVAILABILITY_DEGRADED = 'degraded';
    public const AVAILABILITY_UNAVAILABLE = 'unavailable';
    public const AVAILABILITY_UNKNOWN = 'unknown';

    protected $_data = [
        'id' => '',
        'version' => '',
        'owner' => '',
        'description' => '',
        'inputs' => [],
        'outputs' => [],
        'constraints' => [],
        'risk' => [],
        'evidence' => [],
        'availability' => self::AVAILABILITY_UNKNOWN,
        'metadata' => [],
    ];

    protected $_types = [
        'id' => DataTypes::STRING,
        'version' => DataTypes::STRING,
        'owner' => DataTypes::STRING,
        'description' => DataTypes::STRING,
        'inputs' => DataTypes::ARRAY,
        'outputs' => DataTypes::ARRAY,
        'constraints' => DataTypes::ARRAY,
        'risk' => DataTypes::ARRAY,
        'evidence' => DataTypes::ARRAY,
        'availability' => DataTypes::STRING,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function available(): bool
    {
        return Arr::has([
            self::AVAILABILITY_AVAILABLE,
            self::AVAILABILITY_DEGRADED,
        ], $this->availability, true);
    }
}
