<?php

namespace BlueFission\Automata\LLM\Agent\Governance;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\DevElation as Dev;

class HumanReviewGate
{
    protected mixed $reviewer;

    protected array $requests = [];

    protected array $decisions = [];

    /**
     * Accept an optional callable that can approve, deny, or steer requests.
     */
    public function __construct(?callable $reviewer = null)
    {
        $this->reviewer = $reviewer;
    }

    /**
     * Submit a call for human review and return the normalized decision.
     */
    public function request(array $call): GovernanceDecision
    {
        $call = $this->normalizeCall($call);
        $this->requests[] = $call;

        Dev::do(AgentHook::HUMAN_REVIEW_REQUEST, [
            'request' => $call,
        ]);

        $decision = $this->reviewer
            ? GovernanceDecision::from(($this->reviewer)($call))
            : GovernanceDecision::pending('Awaiting human approval.');

        $decision = $decision->with([
            'review_id' => $call['review_id'],
        ]);

        $this->decisions[] = $decision->toArray();

        Dev::do(AgentHook::HUMAN_REVIEW_DECISION, [
            'request' => $call,
            'decision' => $decision->toArray(),
        ]);

        return $decision;
    }

    /**
     * Return review requests captured by this in-process gate.
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * Return decisions captured by this in-process gate.
     */
    public function decisions(): array
    {
        return $this->decisions;
    }

    /**
     * Build a reusable approval decision.
     */
    public function approve(string $message = ''): GovernanceDecision
    {
        return GovernanceDecision::approved($message);
    }

    /**
     * Build a reusable denial decision.
     */
    public function deny(string $message = ''): GovernanceDecision
    {
        return GovernanceDecision::denied($message);
    }

    /**
     * Build a reusable steering decision with request changes.
     */
    public function steer(array $payload, string $message = ''): GovernanceDecision
    {
        return GovernanceDecision::steered($payload, $message);
    }

    /**
     * Ensure review payloads have stable ids and array fields.
     */
    protected function normalizeCall(array $call): array
    {
        return ToolDefinition::mergeConfig([
            'review_id' => TaskTraceSpan::id('review'),
            'task_id' => null,
            'kind' => 'tool',
            'name' => '',
            'request' => [],
            'metadata' => [],
        ], [
            'review_id' => Arr::hasKey($call, 'review_id') ? $call['review_id'] : TaskTraceSpan::id('review'),
            'task_id' => $call['task_id'] ?? null,
            'kind' => $call['kind'] ?? 'tool',
            'name' => $call['name'] ?? '',
            'request' => Arr::make($call['request'] ?? [])->toArray(),
            'metadata' => Arr::make($call['metadata'] ?? [])->toArray(),
        ]);
    }
}
