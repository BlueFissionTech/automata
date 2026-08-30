<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;

class StrategyEligibility extends RoutingValue
{
    public const CODE_ELIGIBLE = 'eligible';
    public const CODE_INELIGIBLE = 'ineligible';

    public function eligible(): bool
    {
        return (bool)$this->field('eligible');
    }

    public function code(): string
    {
        return (string)$this->field('code');
    }

    public function reasons(): array
    {
        return Arr::make($this->field('reasons') ?? [])->toArray();
    }

    protected function defaults(): array
    {
        return [
            'eligible' => false,
            'code' => self::CODE_INELIGIBLE,
            'reasons' => [],
            'evidence' => [],
            'metadata' => [],
        ];
    }
}
