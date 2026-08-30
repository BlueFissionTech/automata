<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;

class StrategyAdapterResult extends RoutingValue
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const CODE_COMPLETED = 'completed';
    public const CODE_FAILED = 'failed';

    public function succeeded(): bool
    {
        return $this->status() === self::STATUS_COMPLETED;
    }

    public function status(): string
    {
        return (string)$this->field('status');
    }

    public function code(): string
    {
        return (string)$this->field('code');
    }

    public function output(): mixed
    {
        return $this->field('output');
    }

    public function usage(): StrategyUsage
    {
        $usage = $this->field('usage');

        return $usage instanceof StrategyUsage
            ? $usage
            : new StrategyUsage(Arr::make($usage ?? [])->toArray());
    }

    public function confidence(): ?float
    {
        $confidence = $this->field('confidence');

        return $confidence === null ? null : (float)$confidence;
    }

    public function uncertainty(): ?float
    {
        $uncertainty = $this->field('uncertainty');

        return $uncertainty === null ? null : (float)$uncertainty;
    }

    protected function defaults(): array
    {
        return [
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
    }
}
