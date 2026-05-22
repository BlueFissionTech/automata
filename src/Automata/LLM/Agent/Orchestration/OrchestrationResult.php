<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration;

use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\DevElation as Dev;

class OrchestrationResult
{
    protected array $data;

    /**
     * Create a normalized orchestration result.
     */
    public function __construct(array $data = [])
    {
        $this->data = ToolDefinition::mergeConfig([
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

    /**
     * Return completion status.
     */
    public function status(): string
    {
        return (string)$this->data['status'];
    }

    /**
     * Return orchestration output.
     */
    public function output(): mixed
    {
        return $this->data['output'];
    }

    /**
     * Return normalized confidence if workers supplied it.
     */
    public function confidence(): ?float
    {
        return $this->data['confidence'] === null ? null : (float)$this->data['confidence'];
    }

    /**
     * Return storage-ready result data.
     */
    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.orchestration.result.to_array', $this->data);
    }
}
