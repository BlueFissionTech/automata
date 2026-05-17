<?php

namespace BlueFission\Automata\LLM\Agent\Telemetry;

use BlueFission\DevElation as Dev;

class TaskTrace
{
    protected string $taskId;
    protected array $metadata = [];
    /** @var TaskTraceSpan[] */
    protected array $spans = [];
    protected string $outcomeStatus = 'running';

    public function __construct(?string $taskId = null, array $metadata = [])
    {
        $this->taskId = $taskId ?: TaskTraceSpan::id('task');
        $this->metadata = $metadata;
    }

    public static function fromArray(array $data): self
    {
        $trace = new self($data['task_id'] ?? null, $data['metadata'] ?? []);
        $trace->outcomeStatus = $data['outcome_status'] ?? 'running';
        foreach ($data['spans'] ?? [] as $span) {
            $trace->addSpan($span instanceof TaskTraceSpan ? $span : TaskTraceSpan::fromArray($span));
        }

        return $trace;
    }

    public function taskId(): string
    {
        return $this->taskId;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function addSpan(TaskTraceSpan $span): self
    {
        $this->spans[] = $span;
        Dev::do('automata.llm.agent.telemetry.trace_span_added', [
            'task_id' => $this->taskId,
            'span' => $span->toArray(),
        ]);

        return $this;
    }

    public function startSpan(string $kind, string $name, array $metadata = [], ?string $parentSpanId = null): TaskTraceSpan
    {
        return TaskTraceSpan::start($this->taskId, $kind, $name, $metadata, $parentSpanId);
    }

    public function complete(string $status = 'completed'): self
    {
        $this->outcomeStatus = $status;
        return $this;
    }

    public function outcomeStatus(): string
    {
        return $this->outcomeStatus;
    }

    public function spans(): array
    {
        return $this->spans;
    }

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

    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.telemetry.trace.to_array', [
            'task_id' => $this->taskId,
            'metadata' => $this->metadata,
            'outcome_status' => $this->outcomeStatus,
            'spans' => array_map(fn (TaskTraceSpan $span): array => $span->toArray(), $this->spans),
        ]);
    }
}
