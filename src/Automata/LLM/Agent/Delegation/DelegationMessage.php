<?php

namespace BlueFission\Automata\LLM\Agent\Delegation;

class DelegationMessage extends DelegationValue
{
    public const TYPE_PROGRESS = 'progress';
    public const TYPE_PEER_HANDOFF = 'peer_handoff';
    public const TYPE_EVIDENCE = 'evidence';

    protected function defaults(): array
    {
        return [
            'id' => '',
            'delegation_id' => '',
            'type' => self::TYPE_PROGRESS,
            'source_agent_id' => '',
            'target_agent_id' => '',
            'payload' => [],
            'correlation_id' => '',
            'causation_id' => '',
            'trace_id' => '',
            'evidence' => [],
            'diagnostics' => [],
            'metadata' => [],
        ];
    }
}
