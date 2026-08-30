<?php

namespace BlueFission\Automata\LLM\Agent\Delegation;

class DelegationTask extends DelegationValue
{
    protected function defaults(): array
    {
        return [
            'id' => '',
            'goal_id' => null,
            'kind' => 'delegate_task',
            'input' => [],
            'completion_criteria' => [],
            'status' => DelegationStatus::PENDING,
            'metadata' => [],
        ];
    }
}
