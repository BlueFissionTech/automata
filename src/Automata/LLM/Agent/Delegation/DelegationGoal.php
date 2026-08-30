<?php

namespace BlueFission\Automata\LLM\Agent\Delegation;

class DelegationGoal extends DelegationValue
{
    protected function defaults(): array
    {
        return [
            'id' => '',
            'objective' => '',
            'criteria' => [],
            'initiative_id' => null,
            'metadata' => [],
        ];
    }
}
