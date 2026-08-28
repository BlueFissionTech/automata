<?php

namespace BlueFission\Automata\LLM\Agent\Delegation;

class CapabilityGrant extends DelegationValue
{
    public function permits(string $capability, string $sourceAgentId, string $targetAgentId): bool
    {
        return (bool)$this->field('granted')
            && $this->field('capability') === $capability
            && $this->field('source_agent_id') === $sourceAgentId
            && $this->field('target_agent_id') === $targetAgentId;
    }

    protected function defaults(): array
    {
        return [
            'capability' => '',
            'source_agent_id' => '',
            'target_agent_id' => '',
            'granted' => false,
            'transferable' => false,
            'constraints' => [],
            'reason' => '',
        ];
    }
}
