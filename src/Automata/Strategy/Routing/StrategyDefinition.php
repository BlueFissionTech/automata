<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;

class StrategyDefinition extends RoutingValue
{
    public const MODE_DETERMINISTIC = 'deterministic';
    public const MODE_LEARNED = 'learned';
    public const MODE_GENERATIVE = 'generative';

    public const AVAILABILITY_AVAILABLE = 'available';
    public const AVAILABILITY_DEGRADED = 'degraded';
    public const AVAILABILITY_UNAVAILABLE = 'unavailable';
    public const AVAILABILITY_UNKNOWN = 'unknown';

    public function id(): string
    {
        return (string)$this->field('id');
    }

    public function version(): string
    {
        return (string)$this->field('version');
    }

    public function capabilityId(): string
    {
        return (string)$this->field('capability_id');
    }

    public function capabilityVersion(): string
    {
        return (string)$this->field('capability_version');
    }

    public function mode(): string
    {
        return (string)$this->field('mode');
    }

    public function deterministic(): bool
    {
        return $this->mode() === self::MODE_DETERMINISTIC;
    }

    public function sideEffectFree(): bool
    {
        return (bool)$this->field('side_effect_free');
    }

    public function available(): bool
    {
        return Arr::has([
            self::AVAILABILITY_AVAILABLE,
            self::AVAILABILITY_DEGRADED,
        ], (string)$this->field('availability'), true);
    }

    protected function defaults(): array
    {
        return [
            'id' => '',
            'version' => '',
            'capability_id' => '',
            'capability_version' => '',
            'family' => '',
            'mode' => self::MODE_DETERMINISTIC,
            'priority' => 100,
            'side_effect_free' => false,
            'availability' => self::AVAILABILITY_UNKNOWN,
            'inputs' => [],
            'outputs' => [],
            'constraints' => [],
            'evidence' => [],
            'metadata' => [],
        ];
    }
}
