<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;
use BlueFission\DataTypes;

class StrategyAdapterResult extends RoutingValue
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const CODE_COMPLETED = 'completed';
    public const CODE_FAILED = 'failed';

    protected $_data = [
        'status' => self::STATUS_FAILED,
        'code' => self::CODE_FAILED,
        'output' => null,
        'usage' => [],
        'confidence' => null,
        'uncertainty' => null,
        'evidence' => [],
        'diagnostics' => [],
        'metadata' => [],
    ];

    protected $_types = [
        'status' => DataTypes::STRING,
        'code' => DataTypes::STRING,
        'output' => DataTypes::GENERIC,
        'usage' => DataTypes::GENERIC,
        'confidence' => DataTypes::GENERIC,
        'uncertainty' => DataTypes::GENERIC,
        'evidence' => DataTypes::ARRAY,
        'diagnostics' => DataTypes::ARRAY,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function succeeded(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function usage(): StrategyUsage
    {
        $usage = $this->usage;

        return $usage instanceof StrategyUsage
            ? $usage
            : new StrategyUsage(Arr::make($usage ?? [])->toArray());
    }

}
