<?php

namespace BlueFission\Automata\LLM\Agent\Telemetry;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;

class TaskTrace implements IDispatcher
{
    use Dispatches {
        Dispatches::__construct as private __dispatchesConstruct;
    }

    protected string $taskId;

    protected array $metadata = [];

    /** @var TaskTraceSpan[] */
    protected array $spans = [];

    protected string $outcomeStatus = 'running';

    /**
     * Create a trace for one user-visible unit of completed work.
     */
    public function __construct(?string $taskId = null, array $metadata = [])
    {
        $this->__dispatchesConstruct();
        $this->taskId = $taskId ?: TaskTraceSpan::id('task');
        $this->metadata = $metadata;

        Dev::do(CpctHook::TRACE_STARTED, $this->toArray());
        $this->trigger(Event::STARTED);
    }

    /**
     * Rehydrate a trace from stored trace data.
     */
    public static function fromArray(array $data): self
    {
        $trace = new self($data['task_id'] ?? null, $data['metadata'] ?? []);
        $trace->outcomeStatus = $data['outcome_status'] ?? 'running';
        foreach ($data['spans'] ?? [] as $span) {
            $trace->addSpan($span instanceof TaskTraceSpan ? $span : TaskTraceSpan::fromArray($span));
        }

        return $trace;
    }

    /**
     * Return the stable task identifier.
     */
    public function taskId(): string
    {
        return $this->taskId;
    }

    /**
     * Return task-level metadata.
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Add a completed or running span to the trace.
     */
    public function addSpan(TaskTraceSpan $span): self
    {
        $this->spans[] = $span;
        Dev::do(CpctHook::SPAN_ADDED, [
            'task_id' => $this->taskId,
            'span' => $span->toArray(),
        ]);
        $this->trigger(Event::CHANGE);

        return $this;
    }

    /**
     * Start a span that can be finished after the observed work completes.
     */
    public function startSpan(string $kind, string $name, array $metadata = [], ?string $parentSpanId = null): TaskTraceSpan
    {
        $span = TaskTraceSpan::start($this->taskId, $kind, $name, $metadata, $parentSpanId);
        Dev::do(CpctHook::SPAN_STARTED, $span->toArray());

        return $span;
    }

    /**
     * Capture model/API usage payloads from SDK callbacks, logs, or provider responses.
     */
    public function recordModelUsage(string $name, array $usage, array $metadata = []): self
    {
        $span = $this->startSpan(TaskTraceSpan::KIND_MODEL, $name, [
            'source' => $metadata['source'] ?? 'model_api',
            'context' => $metadata,
        ]);

        $inputTokens = (int)($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? $usage['estimated_prompt_tokens'] ?? 0);
        $outputTokens = (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? $usage['estimated_completion_tokens'] ?? 0);

        $metrics = [
            'provider' => $metadata['provider'] ?? null,
            'model' => $metadata['model'] ?? null,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $usage['total_tokens'] ?? $usage['estimated_total_tokens'] ?? ($inputTokens + $outputTokens),
            'cache_hit_tokens' => $usage['cache_hit_tokens'] ?? $usage['cached_input_tokens'] ?? 0,
            'cache_write_tokens' => $usage['cache_write_tokens'] ?? 0,
            'uncached_input_tokens' => $usage['uncached_input_tokens'] ?? 0,
            'batch_tokens' => $usage['batch_tokens'] ?? 0,
            'batchable' => $metadata['batchable'] ?? false,
            'batch_processed' => $metadata['batch_processed'] ?? false,
            'estimated_cost' => $metadata['estimated_cost'] ?? null,
            'metadata' => [
                'usage' => $usage,
                'context' => $metadata,
            ],
        ];

        $this->addSpan($span->finish((string)($metadata['status'] ?? 'completed'), $metrics));
        Dev::do(CpctHook::MODEL_USAGE_CAPTURED, [
            'task_id' => $this->taskId,
            'usage' => $usage,
            'metadata' => $metadata,
        ]);

        return $this;
    }

    /**
     * Capture deterministic batch routing data from queues or service logs.
     */
    public function recordBatchUsage(string $name, int $tokens, bool $processed, array $metadata = []): self
    {
        $this->recordModelUsage($name, [
            'batch_tokens' => $tokens,
            'total_tokens' => $tokens,
        ], ToolDefinition::mergeConfig($metadata, [
            'batchable' => true,
            'batch_processed' => $processed,
            'source' => $metadata['source'] ?? 'batch_log',
        ]));

        Dev::do(CpctHook::BATCH_USAGE_CAPTURED, [
            'task_id' => $this->taskId,
            'name' => $name,
            'tokens' => $tokens,
            'processed' => $processed,
            'metadata' => $metadata,
        ]);

        return $this;
    }

    /**
     * Attach model-tier routing candidate data to the latest matching span.
     */
    public function recordRoutingCandidate(string $spanName, string $candidateModel, float $estimatedCost, bool $metSlo): self
    {
        foreach ($this->spans as $span) {
            if ($span->get('name') !== $spanName) {
                continue;
            }

            $span
                ->set('candidate_model', $candidateModel)
                ->set('candidate_estimated_cost', $estimatedCost)
                ->set('candidate_met_slo', $metSlo);

            Dev::do(CpctHook::ROUTING_CANDIDATE_CAPTURED, [
                'task_id' => $this->taskId,
                'span' => $span->toArray(),
            ]);
            $this->trigger(Event::CHANGE);
        }

        return $this;
    }

    /**
     * Mark the task outcome as completed, abandoned, or failed.
     */
    public function complete(string $status = 'completed'): self
    {
        $this->outcomeStatus = $status;
        Dev::do(CpctHook::TRACE_COMPLETED, $this->toArray());
        $this->trigger(Event::COMPLETE);

        return $this;
    }

    /**
     * Return the normalized task outcome.
     */
    public function outcomeStatus(): string
    {
        return $this->outcomeStatus;
    }

    /**
     * Return trace spans.
     */
    public function spans(): array
    {
        return $this->spans;
    }

    /**
     * Aggregate token, latency, and spend totals for the task.
     */
    public function totals(?CpctPricing $pricing = null): array
    {
        $totals = [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'cache_hit_tokens' => 0,
            'cache_write_tokens' => 0,
            'uncached_input_tokens' => 0,
            'batch_tokens' => 0,
            'wall_time_ms' => 0,
            'model_spend' => 0.0,
            'tool_spend' => 0.0,
            'total_cost' => 0.0,
        ];

        foreach ($this->spans as $span) {
            $row = $span->toArray();
            foreach (['input_tokens', 'output_tokens', 'total_tokens', 'cache_hit_tokens', 'cache_write_tokens', 'uncached_input_tokens', 'batch_tokens'] as $key) {
                $totals[$key] += (int)($row[$key] ?? 0);
            }

            $totals['wall_time_ms'] += (int)($row['duration_ms'] ?? 0);
            $totals['tool_spend'] += (float)($row['tool_spend'] ?? 0);

            $modelCost = $row['estimated_cost'];
            if ($modelCost === null && $pricing) {
                $modelCost = $pricing->costForSpan($row);
            }
            $totals['model_spend'] += (float)($modelCost ?? 0);
        }

        $totals['total_cost'] = $totals['model_spend'] + $totals['tool_spend'];

        return $totals;
    }

    /**
     * Return the trace as normalized storage data.
     */
    public function toArray(): array
    {
        $spans = (new Collection($this->spans))
            ->map(fn (TaskTraceSpan $span): array => $span->toArray())
            ->toArray();

        return Dev::apply('automata.llm.agent.telemetry.trace.to_array', [
            'task_id' => $this->taskId,
            'metadata' => $this->metadata,
            'outcome_status' => $this->outcomeStatus,
            'spans' => $spans,
        ]);
    }
}
