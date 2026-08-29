<?php

namespace BlueFission\Automata\LLM\Agent\Delegation;

use BlueFission\Arr;

class AgentProfile extends DelegationValue
{
    public function id(): string
    {
        return (string)$this->field('id');
    }

    public function capabilities(): array
    {
        return Arr::make($this->field('capabilities') ?? [])->toArray();
    }

    public function hasCapability(string $capability): bool
    {
        return Arr::has($this->capabilities(), $capability, true);
    }

    protected function defaults(): array
    {
        return [
            'id' => '',
            'role' => 'specialist',
            'capabilities' => [],
            'parent_id' => null,
            'peer_ids' => [],
            'metadata' => [],
        ];
    }
}
