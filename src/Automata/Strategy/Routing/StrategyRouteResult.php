<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\DataTypes;

class StrategyRouteResult extends RoutingValue
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DENIED = 'denied';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_FAILED = 'failed';

    protected $_data = [
        'status' => self::STATUS_EXHAUSTED,
        'code' => '',
        'request_id' => '',
        'subject_id' => '',
        'capability_id' => '',
        'capability_version' => '',
        'selected_strategy' => null,
        'output' => null,
        'attempts' => [],
        'usage' => [],
        'limits' => [],
        'selection_advice' => [],
        'escalated' => false,
        'escalation_history' => [],
        'authorization' => [],
        'evidence' => [],
        'diagnostics' => [],
        'correlation_id' => '',
        'causation_id' => '',
        'trace_id' => '',
        'metadata' => [],
    ];

    protected $_types = [
        'status' => DataTypes::STRING,
        'code' => DataTypes::STRING,
        'request_id' => DataTypes::STRING,
        'subject_id' => DataTypes::STRING,
        'capability_id' => DataTypes::STRING,
        'capability_version' => DataTypes::STRING,
        'selected_strategy' => DataTypes::GENERIC,
        'output' => DataTypes::GENERIC,
        'attempts' => DataTypes::ARRAY,
        'usage' => DataTypes::ARRAY,
        'limits' => DataTypes::ARRAY,
        'selection_advice' => DataTypes::ARRAY,
        'escalated' => DataTypes::BOOLEAN,
        'escalation_history' => DataTypes::ARRAY,
        'authorization' => DataTypes::ARRAY,
        'evidence' => DataTypes::ARRAY,
        'diagnostics' => DataTypes::ARRAY,
        'correlation_id' => DataTypes::STRING,
        'causation_id' => DataTypes::STRING,
        'trace_id' => DataTypes::STRING,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function completed(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
