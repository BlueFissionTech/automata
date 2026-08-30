<?php

namespace BlueFission\Automata\LLM\Agent\Capability;

use BlueFission\Arr;

class CapabilityDefinition extends CapabilityValue
{
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

    public function owner(): string
    {
        return (string)$this->field('owner');
    }

    public function availability(): string
    {
        return (string)$this->field('availability');
    }

    public function available(): bool
    {
        return Arr::has([
            self::AVAILABILITY_AVAILABLE,
            self::AVAILABILITY_DEGRADED,
        ], $this->availability(), true);
    }

    public function constraints(): array
    {
        return Arr::make($this->field('constraints') ?? [])->toArray();
    }

    public function evidence(): array
    {
        return Arr::make($this->field('evidence') ?? [])->toArray();
    }

    protected function defaults(): array
    {
        return [
            'id' => '',
            'version' => '',
            'owner' => '',
            'description' => '',
            'inputs' => [],
            'outputs' => [],
            'constraints' => [],
            'risk' => [],
            'evidence' => [],
            'availability' => self::AVAILABILITY_UNKNOWN,
            'metadata' => [],
        ];
    }
}
