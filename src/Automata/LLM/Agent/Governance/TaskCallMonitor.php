<?php

namespace BlueFission\Automata\LLM\Agent\Governance;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\DevElation as Dev;
use Throwable;

class TaskCallMonitor
{
    protected ?TaskTrace $trace;

    protected TaskCallPolicy $policy;

    protected ?HumanReviewGate $humanReviewGate;

    /**
     * Create an observable governance boundary for task-scoped external calls.
     */
    public function __construct(?TaskTrace $trace = null, TaskCallPolicy|array|null $policy = null, ?HumanReviewGate $humanReviewGate = null)
    {
        $this->trace = $trace;
        $this->policy = $policy instanceof TaskCallPolicy ? $policy : new TaskCallPolicy($policy ?? []);
        $this->humanReviewGate = $humanReviewGate;
    }

    /**
     * Attach the task trace that should receive call spans.
     */
    public function useTrace(?TaskTrace $trace): void
    {
        $this->trace = $trace;
    }

    /**
     * Attach or replace the governance policy.
     */
    public function usePolicy(TaskCallPolicy|array $policy): void
    {
        $this->policy = $policy instanceof TaskCallPolicy ? $policy : new TaskCallPolicy($policy);
    }

    /**
     * Attach or replace the human review gate.
     */
    public function useHumanReviewGate(?HumanReviewGate $gate): void
    {
        $this->humanReviewGate = $gate;
    }

    /**
     * Observe, govern, execute, and trace one task-scoped external call.
     */
    public function observe(string $kind, string $name, callable $operation, array $request = [], array $metadata = []): array
    {
        $call = $this->callPayload($kind, $name, $request, $metadata);
        $span = $this->trace?->startSpan($kind, $name, [
            'call_id' => $call['call_id'],
            'governance' => 'observed',
            'metadata' => $metadata,
        ]);

        Dev::do(AgentHook::PRE_TASK_CALL, [
            'call' => $call,
        ]);

        $decision = $this->decisionFor($call);
        if ($decision->isSteered()) {
            $call['request'] = ToolDefinition::mergeConfig($call['request'], $decision->payload());
            Dev::do(AgentHook::TASK_CALL_STEERED, [
                'call' => $call,
                'decision' => $decision->toArray(),
            ]);
        }

        if (!$decision->allowsExecution()) {
            $result = $this->blockedResult($call, $decision);
            $this->finishSpan($span, 'failed', $call, $result, $decision);
            $this->emitPostCall($call, $result, $decision);
            return $result;
        }

        try {
            $payload = $operation($call['request'], $call, $decision);
            $result = $this->successResult($call, $payload, $decision);
            $this->finishSpan($span, 'completed', $call, $result, $decision);
            $this->emitPostCall($call, $result, $decision);

            return $result;
        } catch (Throwable $exception) {
            $result = $this->errorResult($call, $exception, $decision);
            $this->finishSpan($span, 'failed', $call, $result, $decision);
            $this->emitPostCall($call, $result, $decision);

            return $result;
        }
    }

    /**
     * Record an already-completed external call from service logs or callbacks.
     */
    public function record(string $kind, string $name, array $request = [], array $response = [], array $metadata = []): self
    {
        if ($this->trace) {
            $this->trace->recordTaskCall($kind, $name, $request, $response, $metadata);
        }

        Dev::do(AgentHook::POST_TASK_CALL, [
            'call' => $this->callPayload($kind, $name, $request, $metadata),
            'result' => [
                'ok' => (bool)($response['ok'] ?? true),
                'status' => $response['status'] ?? 'recorded',
                'payload' => $response,
            ],
        ]);

        return $this;
    }

    /**
     * Build the normalized call payload.
     */
    protected function callPayload(string $kind, string $name, array $request, array $metadata): array
    {
        return [
            'call_id' => TaskTraceSpan::id('call'),
            'task_id' => $this->trace?->taskId(),
            'kind' => $kind,
            'name' => $name,
            'request' => $request,
            'metadata' => $metadata,
        ];
    }

    /**
     * Run policy and optional human review for a call.
     */
    protected function decisionFor(array $call): GovernanceDecision
    {
        $decision = $this->policy->assess($call);
        if ($decision->isPending() && $this->humanReviewGate) {
            return $this->humanReviewGate->request($call);
        }

        return $decision;
    }

    /**
     * Return a structured result for an allowed call.
     */
    protected function successResult(array $call, mixed $payload, GovernanceDecision $decision): array
    {
        return [
            'ok' => true,
            'status' => 'completed',
            'payload' => $payload,
            'error' => null,
            'decision' => $decision->toArray(),
            'call' => $call,
        ];
    }

    /**
     * Return a structured result for a blocked or pending call.
     */
    protected function blockedResult(array $call, GovernanceDecision $decision): array
    {
        $code = $decision->isPending() ? 'human_review_required' : 'governance_denied';
        Dev::do(AgentHook::TASK_CALL_BLOCKED, [
            'call' => $call,
            'decision' => $decision->toArray(),
        ]);

        return [
            'ok' => false,
            'status' => $decision->status(),
            'payload' => null,
            'error' => [
                'code' => $code,
                'message' => $decision->message(),
                'details' => $decision->payload(),
            ],
            'decision' => $decision->toArray(),
            'call' => $call,
        ];
    }

    /**
     * Return a structured result for a failed call.
     */
    protected function errorResult(array $call, Throwable $exception, GovernanceDecision $decision): array
    {
        return [
            'ok' => false,
            'status' => 'failed',
            'payload' => null,
            'error' => [
                'code' => 'task_call_failed',
                'message' => $exception->getMessage(),
                'details' => [
                    'kind' => $call['kind'],
                    'name' => $call['name'],
                ],
            ],
            'decision' => $decision->toArray(),
            'call' => $call,
        ];
    }

    /**
     * Complete a trace span when a trace is available.
     */
    protected function finishSpan(?TaskTraceSpan $span, string $status, array $call, array $result, GovernanceDecision $decision): void
    {
        if (!$span || !$this->trace) {
            return;
        }

        $metadata = ToolDefinition::mergeConfig($call['metadata'], [
            'call_id' => $call['call_id'],
            'governance_status' => $decision->status(),
            'decision' => $decision->toArray(),
            'request' => $call['request'],
            'result_status' => $result['status'],
        ]);

        $this->trace->addSpan($span->finish($status, [
            'outcome_status' => $result['status'],
            'metadata' => $metadata,
            'error' => $result['error'],
        ]));
    }

    /**
     * Emit the post-call lifecycle hook.
     */
    protected function emitPostCall(array $call, array $result, GovernanceDecision $decision): void
    {
        Dev::do(AgentHook::POST_TASK_CALL, [
            'call' => $call,
            'result' => $result,
            'decision' => $decision->toArray(),
        ]);
    }
}
