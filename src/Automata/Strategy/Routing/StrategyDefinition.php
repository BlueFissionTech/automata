<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;
use BlueFission\DataTypes;

class StrategyDefinition extends RoutingValue
{
    public const MODE_DETERMINISTIC = 'deterministic';
    public const MODE_LEARNED = 'learned';
    public const MODE_GENERATIVE = 'generative';

    public const AVAILABILITY_AVAILABLE = 'available';
    public const AVAILABILITY_DEGRADED = 'degraded';
    public const AVAILABILITY_UNAVAILABLE = 'unavailable';
    public const AVAILABILITY_UNKNOWN = 'unknown';

    protected $_data = [
        'id' => '',
        'version' => '',
        'capability_id' => '',
        'capability_version' => '',
        'family' => '',
        'mode' => self::MODE_DETERMINISTIC,
        'priority' => 100,
        'side_effect_free' => false,
        'availability' => self::AVAILABILITY_UNKNOWN,
        'inputs' => [],
        'outputs' => [],
        'constraints' => [],
        'evidence' => [],
        'metadata' => [],
    ];

    protected $_types = [
        'id' => DataTypes::STRING,
        'version' => DataTypes::STRING,
        'capability_id' => DataTypes::STRING,
        'capability_version' => DataTypes::STRING,
        'family' => DataTypes::STRING,
        'mode' => DataTypes::STRING,
        'priority' => DataTypes::INTEGER,
        'side_effect_free' => DataTypes::BOOLEAN,
        'availability' => DataTypes::STRING,
        'inputs' => DataTypes::ARRAY,
        'outputs' => DataTypes::ARRAY,
        'constraints' => DataTypes::ARRAY,
        'evidence' => DataTypes::ARRAY,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function deterministic(): bool
    {
        return $this->mode === self::MODE_DETERMINISTIC;
    }

    public function available(): bool
    {
        return Arr::has([
            self::AVAILABILITY_AVAILABLE,
            self::AVAILABILITY_DEGRADED,
        ], $this->availability, true);
    }
}
