<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;

class StrategyRouteResult extends RoutingValue
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DENIED = 'denied';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_FAILED = 'failed';

    public function status(): string
    {
        return (string)$this->field('status');
    }

    public function code(): string
    {
        return (string)$this->field('code');
    }

    public function selectedStrategy(): ?array
    {
        $selected = $this->field('selected_strategy');

        return $selected === null ? null : Arr::make($selected)->toArray();
    }

    public function output(): mixed
    {
        return $this->field('output');
    }

    public function attempts(): array
    {
        return Arr::make($this->field('attempts') ?? [])->toArray();
    }

    public function usage(): array
    {
        return (new StrategyUsage(Arr::make($this->field('usage') ?? [])->toArray()))->toArray();
    }

    public function escalationHistory(): array
    {
        return Arr::make($this->field('escalation_history') ?? [])->toArray();
    }

    protected function defaults(): array
    {
        return [
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
    }
}
