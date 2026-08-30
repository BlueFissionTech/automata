<?php

namespace BlueFission\Automata\LLM\Generation;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use DateTimeImmutable;
use Throwable;

class GenerationRunRequest extends GenerationValue
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);

        $runId = (string)$this->field('run_id');
        if ($runId === '') {
            $runId = TaskTraceSpan::id('generation_run');
            $this->field('run_id', $runId);
        }

        foreach (['task_id', 'correlation_id', 'trace_id'] as $field) {
            if ((string)$this->field($field) === '') {
                $this->field($field, $runId);
            }
        }
    }

    public function runId(): string
    {
        return (string)$this->field('run_id');
    }

    public function taskId(): string
    {
        return (string)$this->field('task_id');
    }

    public function input(): mixed
    {
        return $this->field('input');
    }

    public function policy(): array
    {
        return Arr::make($this->field('policy') ?? [])->toArray();
    }

    public function evidence(): array
    {
        return Arr::make($this->field('evidence') ?? [])->toArray();
    }

    public function isCancelled(): bool
    {
        return (bool)$this->field('cancelled');
    }

    public function isTimedOut(?DateTimeImmutable $now = null): bool
    {
        $deadline = $this->field('deadline');
        if (!$deadline) {
            return false;
        }

        try {
            return new DateTimeImmutable((string)$deadline) <= ($now ?? new DateTimeImmutable());
        } catch (Throwable) {
            return true;
        }
    }

    protected function defaults(): array
    {
        return [
            'contract_version' => '1.0',
            'run_id' => '',
            'task_id' => '',
            'correlation_id' => '',
            'causation_id' => '',
            'trace_id' => '',
            'input' => null,
            'profile' => [],
            'constraints' => [],
            'policy' => [],
            'evidence' => [],
            'context' => [],
            'cancelled' => false,
            'deadline' => null,
            'metadata' => [],
        ];
    }
}
