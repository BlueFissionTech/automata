<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\DataTypes;

class StrategyEligibility extends RoutingValue
{
    public const CODE_ELIGIBLE = 'eligible';
    public const CODE_INELIGIBLE = 'ineligible';

    protected $_data = [
        'eligible' => false,
        'code' => self::CODE_INELIGIBLE,
        'reasons' => [],
        'evidence' => [],
        'metadata' => [],
    ];

    protected $_types = [
        'eligible' => DataTypes::BOOLEAN,
        'code' => DataTypes::STRING,
        'reasons' => DataTypes::ARRAY,
        'evidence' => DataTypes::ARRAY,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;
}
