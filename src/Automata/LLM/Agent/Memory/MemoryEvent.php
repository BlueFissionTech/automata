<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\DevElation as Dev;

class MemoryEvent
{
    public const SESSION_START = 'SessionStart';
    public const USER_PROMPT_SUBMIT = 'UserPromptSubmit';
    public const PRE_TOOL_USE = 'PreToolUse';
    public const POST_TOOL_USE = 'PostToolUse';
    public const STOP = 'Stop';

    protected array $data = [];

    public function __construct(string $event, array $payload = [], array $context = [])
    {
        $this->data = array_replace_recursive([
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

    public function event(): string
    {
        return (string)$this->data['event'];
    }

    public function sequence(): ?int
    {
        return isset($this->data['sequence']) ? (int)$this->data['sequence'] : null;
    }

    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.memory.event.to_array', $this->data);
    }
}
