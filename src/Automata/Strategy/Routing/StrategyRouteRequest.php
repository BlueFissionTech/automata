<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;

class StrategyRouteRequest extends RoutingValue
{
    public const ESCALATE_NONE = 'none';
    public const ESCALATE_NEXT_ELIGIBLE = 'next_eligible';

    public function id(): string
    {
        return (string)$this->field('id');
    }

    public function subjectId(): string
    {
        return (string)$this->field('subject_id');
    }

    public function capabilityId(): string
    {
        return (string)$this->field('capability_id');
    }

    public function capabilityVersion(): string
    {
        return (string)$this->field('capability_version');
    }

    public function candidates(): array
    {
        return Arr::make($this->field('candidates') ?? [])->toArray();
    }

    public function input(): mixed
    {
        return $this->field('input');
    }

    public function limits(): array
    {
        return Arr::make($this->field('limits') ?? [])->toArray();
    }

    public function allowsMode(string $mode): bool
    {
        return Arr::has(Arr::make($this->field('allowed_modes') ?? [])->toArray(), $mode, true);
    }

    public function deterministicPreferred(): bool
    {
        return (bool)$this->field('deterministic_preferred');
    }

    public function canEscalate(): bool
    {
        return $this->field('escalation_policy') === self::ESCALATE_NEXT_ELIGIBLE;
    }

    protected function defaults(): array
    {
        return [
            'id' => '',
            'subject_id' => '',
            'capability_id' => '',
            'capability_version' => '',
            'candidates' => [],
            'input' => null,
            'fixtures' => [],
            'eligibility' => [],
            'limits' => [],
            'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC],
            'deterministic_preferred' => true,
            'escalation_policy' => self::ESCALATE_NONE,
            'correlation_id' => '',
            'causation_id' => '',
            'trace_id' => '',
            'metadata' => [],
        ];
    }
}
