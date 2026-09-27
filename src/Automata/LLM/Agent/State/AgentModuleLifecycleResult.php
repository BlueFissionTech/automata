<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Arr;

final class AgentModuleLifecycleResult extends AgentModuleResult
{
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const DENIED = 'denied';
    public const FAILED = 'failed';
    public const RESOURCE_LIMITED = 'resource_limited';
    public const UNSUPPORTED = 'unsupported';

    public function status(): string
    {
        return (string)($this->data['status'] ?? self::FAILED);
    }

    public function diagnostics(): array
    {
        return Arr::make($this->data['diagnostics'] ?? [])->toArray();
    }

    public function execution(): array
    {
        return Arr::make($this->data['execution'] ?? [])->toArray();
    }

    public function lineage(): array
    {
        return Arr::make($this->data['lineage'] ?? [])->toArray();
    }

    public function canApplyWrites(): bool
    {
        return $this->status() === self::COMPLETED;
    }
}
