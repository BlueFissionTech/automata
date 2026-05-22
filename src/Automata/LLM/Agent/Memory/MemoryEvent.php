<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\DevElation as Dev;

class MemoryEvent
{
    /** @deprecated Use AgentHook::SESSION_START. */
    public const SESSION_START = AgentHook::SESSION_START;
    /** @deprecated Use AgentHook::USER_PROMPT_SUBMIT. */
    public const USER_PROMPT_SUBMIT = AgentHook::USER_PROMPT_SUBMIT;
    /** @deprecated Use AgentHook::PRE_TOOL_USE. */
    public const PRE_TOOL_USE = AgentHook::PRE_TOOL_USE;
    /** @deprecated Use AgentHook::POST_TOOL_USE. */
    public const POST_TOOL_USE = AgentHook::POST_TOOL_USE;
    /** @deprecated Use AgentHook::TURN_STOP. */
    public const STOP = AgentHook::TURN_STOP;

    protected array $data = [];

    /**
     * Create a persisted lifecycle event from Agent hook data.
     */
    public function __construct(string $event, array $payload = [], array $context = [])
    {
        $this->data = ToolDefinition::mergeConfig([
            'event_id' => TaskTraceSpan::id('memory'),
            'event' => $event,
            'session_id' => $context['session_id'] ?? null,
            'task_id' => $context['task_id'] ?? null,
            'sequence' => $context['sequence'] ?? null,
            'client' => $context['client'] ?? 'automata',
            'occurred_at' => microtime(true),
            'payload' => $payload,
        ], $context);
    }

    /**
     * Return the lifecycle hook name.
     */
    public function event(): string
    {
        return (string)$this->data['event'];
    }

    /**
     * Return the deterministic sequence number within the session.
     */
    public function sequence(): ?int
    {
        return Arr::hasKey($this->data, 'sequence') ? (int)$this->data['sequence'] : null;
    }

    /**
     * Return storage-ready event data.
     */
    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.memory.event.to_array', $this->data);
    }
}
