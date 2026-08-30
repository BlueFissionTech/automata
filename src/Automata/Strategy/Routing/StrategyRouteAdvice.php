<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;

class StrategyRouteAdvice extends RoutingValue
{
    public function policy(): string
    {
        return (string)$this->field('policy');
    }

    public function rankings(): array
    {
        return Arr::make($this->field('rankings') ?? [])->toArray();
    }

    public function ranking(string $strategyId, string $strategyVersion): ?array
    {
        $ranking = $this->rankings()[self::key($strategyId, $strategyVersion)] ?? null;

        return $ranking === null ? null : Arr::make($ranking)->toArray();
    }

    public function score(string $strategyId, string $strategyVersion): ?float
    {
        $ranking = $this->ranking($strategyId, $strategyVersion);

        return $ranking === null || !isset($ranking['score'])
            ? null
            : (float)$ranking['score'];
    }

    public static function key(string $strategyId, string $strategyVersion): string
    {
        return $strategyId . '@' . $strategyVersion;
    }

    protected function defaults(): array
    {
        return [
            'policy' => StrategyRouteRequest::SELECTION_DECLARED,
            'rankings' => [],
            'evidence' => [],
            'diagnostics' => [],
            'metadata' => [],
        ];
    }
}
