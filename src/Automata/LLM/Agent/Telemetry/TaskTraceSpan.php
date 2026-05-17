<?php

namespace BlueFission\Automata\LLM\Agent\Telemetry;

use BlueFission\DevElation as Dev;

class TaskTraceSpan
{
    public const KIND_AGENT = 'agent';
    public const KIND_MODEL = 'model';
    public const KIND_TOOL = 'tool';
    public const KIND_ORCHESTRATION = 'orchestration';

    protected array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = array_replace_recursive($this->defaults(), $data);
        if (!$this->data['span_id']) {
            $this->data['span_id'] = self::id('span');
        }
        if (!$this->data['started_at']) {
            $this->data['started_at'] = microtime(true);
        }
    }

    public static function start(string $taskId, string $kind, string $name, array $metadata = [], ?string $parentSpanId = null): self
    {
        return new self([
            'task_id' => $taskId,
            'kind' => $kind,
            'name' => $name,
            'parent_span_id' => $parentSpanId,
            'metadata' => $metadata,
        ]);
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function id(string $prefix = 'id'): string
    {
        try {
            return $prefix . '_' . bin2hex(random_bytes(8));
        } catch (\Throwable) {
            return uniqid($prefix . '_', true);
        }
    }

    public function finish(string $status = 'completed', array $metrics = []): self
    {
        $endedAt = $metrics['ended_at'] ?? microtime(true);
        $this->data = array_replace_recursive($this->data, $metrics);
        $this->data['status'] = $status;
        $this->data['ended_at'] = $endedAt;
        $this->data['duration_ms'] = max(0, (int)round(((float)$endedAt - (float)$this->data['started_at']) * 1000));

        Dev::do('automata.llm.agent.telemetry.span_finished', $this->toArray());

        return $this;
    }

    public function fail(string $code, string $message, array $details = []): self
    {
        return $this->finish('failed', [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ]);
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.telemetry.span.to_array', $this->data);
    }

    protected function defaults(): array
    {
        return [
            'task_id' => null,
            'span_id' => null,
            'parent_span_id' => null,
            'kind' => self::KIND_AGENT,
            'name' => '',
            'status' => 'running',
            'started_at' => null,
            'ended_at' => null,
            'duration_ms' => 0,
            'provider' => null,
            'model' => null,
            'model_tier' => null,
            'candidate_model' => null,
            'candidate_estimated_cost' => null,
            'candidate_met_slo' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'cache_hit_tokens' => 0,
            'cache_write_tokens' => 0,
            'uncached_input_tokens' => 0,
            'batch_tokens' => 0,
            'batchable' => false,
            'batch_processed' => false,
            'estimated_cost' => null,
            'tool_spend' => 0.0,
            'outcome_status' => null,
            'metadata' => [],
            'error' => null,
        ];
    }
}
