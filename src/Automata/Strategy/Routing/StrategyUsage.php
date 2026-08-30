<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Num;

class StrategyUsage extends RoutingValue
{
    public function cost(): float
    {
        return Num::max(0.0, (float)$this->field('cost'));
    }

    public function latencyMs(): int
    {
        return (int)Num::max(0, (int)$this->field('latency_ms'));
    }

    public function energy(): float
    {
        return Num::max(0.0, (float)$this->field('energy'));
    }

    public function invocations(): int
    {
        return (int)Num::max(0, (int)$this->field('invocations'));
    }

    public function plus(self $usage): self
    {
        return new self([
            'cost' => $this->cost() + $usage->cost(),
            'latency_ms' => $this->latencyMs() + $usage->latencyMs(),
            'energy' => $this->energy() + $usage->energy(),
            'invocations' => $this->invocations() + $usage->invocations(),
        ]);
    }

    public function withMinimumInvocations(int $minimum = 1): self
    {
        return new self([
            'cost' => $this->cost(),
            'latency_ms' => $this->latencyMs(),
            'energy' => $this->energy(),
            'invocations' => Num::max($minimum, $this->invocations()),
        ]);
    }

    public function within(array $limits): bool
    {
        return (!isset($limits['max_cost']) || $this->cost() <= (float)$limits['max_cost'])
            && (!isset($limits['max_latency_ms']) || $this->latencyMs() <= (int)$limits['max_latency_ms'])
            && (!isset($limits['max_energy']) || $this->energy() <= (float)$limits['max_energy'])
            && (!isset($limits['max_invocations']) || $this->invocations() <= (int)$limits['max_invocations']);
    }

    public function toArray(): array
    {
        return [
            'cost' => $this->cost(),
            'latency_ms' => $this->latencyMs(),
            'energy' => $this->energy(),
            'invocations' => $this->invocations(),
        ];
    }

    protected function defaults(): array
    {
        return [
            'cost' => 0.0,
            'latency_ms' => 0,
            'energy' => 0.0,
            'invocations' => 0,
        ];
    }
}
