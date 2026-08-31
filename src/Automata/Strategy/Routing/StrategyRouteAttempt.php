<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\DataTypes;

class StrategyRouteAttempt extends RoutingValue
{
    protected $_data = [
        'strategy_id' => '',
        'strategy_version' => '',
        'mode' => '',
        'status' => 'skipped',
        'code' => '',
        'eligible' => false,
        'executed' => false,
        'escalated' => false,
        'estimate' => [],
        'actual_usage' => [],
        'selection_score' => null,
        'confidence' => null,
        'uncertainty' => null,
        'reasons' => [],
        'evidence' => [],
        'diagnostics' => [],
        'metadata' => [],
    ];

    protected $_types = [
        'strategy_id' => DataTypes::STRING,
        'strategy_version' => DataTypes::STRING,
        'mode' => DataTypes::STRING,
        'status' => DataTypes::STRING,
        'code' => DataTypes::STRING,
        'eligible' => DataTypes::BOOLEAN,
        'executed' => DataTypes::BOOLEAN,
        'escalated' => DataTypes::BOOLEAN,
        'estimate' => DataTypes::ARRAY,
        'actual_usage' => DataTypes::ARRAY,
        'selection_score' => DataTypes::GENERIC,
        'confidence' => DataTypes::GENERIC,
        'uncertainty' => DataTypes::GENERIC,
        'reasons' => DataTypes::ARRAY,
        'evidence' => DataTypes::ARRAY,
        'diagnostics' => DataTypes::ARRAY,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;
}
