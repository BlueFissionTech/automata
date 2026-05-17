<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration;

use BlueFission\DevElation as Dev;

class OrchestrationResult
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = array_replace_recursive([
            'status' => 'completed',
            'pattern' => null,
            'output' => null,
            'worker_results' => [],
            'conflicts' => [],
            'iterations' => 1,
            'confidence' => null,
            'metadata' => [],
        ], $data);
    }

    public function status(): string
    {
        return (string)$this->data['status'];
    }

    public function output(): mixed
    {
        return $this->data['output'];
    }

    public function confidence(): ?float
    {
        return $this->data['confidence'] === null ? null : (float)$this->data['confidence'];
    }

    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.orchestration.result.to_array', $this->data);
    }
}
