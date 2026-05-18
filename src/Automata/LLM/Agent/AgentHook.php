<?php

namespace BlueFission\Automata\LLM\Agent;

class AgentHook
{
    public const SESSION_START = 'automata.llm.agent.session_start';
    public const USER_PROMPT_SUBMIT = 'automata.llm.agent.user_prompt_submit';
    public const PERMISSION_REQUEST = 'automata.llm.agent.permission_request';
    public const PRE_TOOL_USE = 'automata.llm.agent.pre_tool_use';
    public const POST_TOOL_USE = 'automata.llm.agent.post_tool_use';
    public const PRE_TASK_CALL = 'automata.llm.agent.pre_task_call';
    public const POST_TASK_CALL = 'automata.llm.agent.post_task_call';
    public const TASK_CALL_BLOCKED = 'automata.llm.agent.task_call_blocked';
    public const TASK_CALL_STEERED = 'automata.llm.agent.task_call_steered';
    public const HUMAN_REVIEW_REQUEST = 'automata.llm.agent.human_review_request';
    public const HUMAN_REVIEW_DECISION = 'automata.llm.agent.human_review_decision';
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
            self::PRE_TASK_CALL,
            self::POST_TASK_CALL,
            self::TASK_CALL_BLOCKED,
            self::TASK_CALL_STEERED,
            self::HUMAN_REVIEW_REQUEST,
            self::HUMAN_REVIEW_DECISION,
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

    /**
     * Return task-call governance hooks for MCP, RPC, and API boundaries.
     */
    public static function taskCalls(): array
    {
        return [
            self::PRE_TASK_CALL,
            self::POST_TASK_CALL,
            self::TASK_CALL_BLOCKED,
            self::TASK_CALL_STEERED,
            self::HUMAN_REVIEW_REQUEST,
            self::HUMAN_REVIEW_DECISION,
        ];
    }
}
