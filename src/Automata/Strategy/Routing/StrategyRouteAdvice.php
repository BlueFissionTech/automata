<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;
use BlueFission\DataTypes;

class StrategyRouteAdvice extends RoutingValue
{
    protected $_data = [
        'policy' => StrategyRouteRequest::SELECTION_DECLARED,
        'rankings' => [],
        'evidence' => [],
        'diagnostics' => [],
        'metadata' => [],
    ];

    protected $_types = [
        'policy' => DataTypes::STRING,
        'rankings' => DataTypes::ARRAY,
        'evidence' => DataTypes::ARRAY,
        'diagnostics' => DataTypes::ARRAY,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function ranking(string $strategyId, string $strategyVersion): ?array
    {
        $ranking = $this->rankings[self::key($strategyId, $strategyVersion)] ?? null;

        return $ranking === null ? null : Arr::make($ranking)->toArray();
    }

    public function score(string $strategyId, string $strategyVersion): ?float
    {
        $ranking = $this->ranking($strategyId, $strategyVersion);

        return $ranking === null || !Arr::hasKey($ranking, 'score')
            ? null
            : (float)$ranking['score'];
    }

    public static function key(string $strategyId, string $strategyVersion): string
    {
        return $strategyId . '@' . $strategyVersion;
    }

}
