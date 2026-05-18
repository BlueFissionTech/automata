<?php

namespace BlueFission\Automata\LLM\Agent;

class AgentHook
{
    public const SESSION_START = 'automata.llm.agent.session_start';
    public const USER_PROMPT_SUBMIT = 'automata.llm.agent.user_prompt_submit';
    public const PERMISSION_REQUEST = 'automata.llm.agent.permission_request';
    public const PRE_TOOL_USE = 'automata.llm.agent.pre_tool_use';
    public const POST_TOOL_USE = 'automata.llm.agent.post_tool_use';
    public const TURN_STOP = 'automata.llm.agent.turn_stop';

    /**
     * Return all lifecycle hook names in their natural execution order.
     */
    public static function all(): array
    {
        return [
            self::SESSION_START,
            self::USER_PROMPT_SUBMIT,
            self::PERMISSION_REQUEST,
            self::PRE_TOOL_USE,
            self::POST_TOOL_USE,
            self::TURN_STOP,
        ];
    }

    /**
     * Return tool lifecycle hooks that bracket deterministic execution.
     */
    public static function toolUse(): array
    {
        return [
            self::PERMISSION_REQUEST,
            self::PRE_TOOL_USE,
            self::POST_TOOL_USE,
        ];
    }
}
