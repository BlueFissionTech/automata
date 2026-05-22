<?php

namespace BlueFission\Automata\LLM\Agent\Telemetry;

class CpctHook
{
    public const TRACE_STARTED = 'automata.llm.agent.telemetry.trace_started';
    public const TRACE_COMPLETED = 'automata.llm.agent.telemetry.trace_completed';
    public const SPAN_STARTED = 'automata.llm.agent.telemetry.span_started';
    public const SPAN_ADDED = 'automata.llm.agent.telemetry.span_added';
    public const SPAN_FINISHED = 'automata.llm.agent.telemetry.span_finished';
    public const MODEL_USAGE_CAPTURED = 'automata.llm.agent.telemetry.model_usage_captured';
    public const BATCH_USAGE_CAPTURED = 'automata.llm.agent.telemetry.batch_usage_captured';
    public const ROUTING_CANDIDATE_CAPTURED = 'automata.llm.agent.telemetry.routing_candidate_captured';
    public const TASK_CALL_CAPTURED = 'automata.llm.agent.telemetry.task_call_captured';
    public const REPORT_BUILT = 'automata.llm.agent.telemetry.report_built';

    /**
     * Return all CPCT telemetry hook names.
     */
    public static function all(): array
    {
        return [
            self::TRACE_STARTED,
            self::TRACE_COMPLETED,
            self::SPAN_STARTED,
            self::SPAN_ADDED,
            self::SPAN_FINISHED,
            self::MODEL_USAGE_CAPTURED,
            self::BATCH_USAGE_CAPTURED,
            self::ROUTING_CANDIDATE_CAPTURED,
            self::TASK_CALL_CAPTURED,
            self::REPORT_BUILT,
        ];
    }
}
