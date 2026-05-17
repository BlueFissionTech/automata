<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration;

class OrchestrationConfig
{
    public const SEQUENTIAL = 'sequential';
    public const FAN_OUT = 'fan_out';
    public const HIERARCHICAL = 'hierarchical';
    public const REFLEXIVE = 'reflexive';

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive([
            'pattern' => self::SEQUENTIAL,
            'workers' => [],
            'supervisor' => null,
            'producer' => null,
            'verifier' => null,
            'fallback' => null,
            'merge_policy' => 'collect_conflicts',
            'confidence_threshold' => 0.75,
            'max_iterations' => 3,
            'model_tiers' => [],
        ], $config);
    }

    public function pattern(): string
    {
        return (string)$this->config['pattern'];
    }

    public function workers(): array
    {
        return $this->config['workers'] ?? [];
    }

    public function supervisor(): mixed
    {
        return $this->config['supervisor'] ?? null;
    }

    public function producer(): mixed
    {
        return $this->config['producer'] ?? null;
    }

    public function verifier(): mixed
    {
        return $this->config['verifier'] ?? null;
    }

    public function fallback(): mixed
    {
        return $this->config['fallback'] ?? null;
    }

    public function mergePolicy(): string
    {
        return (string)$this->config['merge_policy'];
    }

    public function confidenceThreshold(): float
    {
        return (float)$this->config['confidence_threshold'];
    }

    public function maxIterations(): int
    {
        return max(1, (int)$this->config['max_iterations']);
    }

    public function toArray(): array
    {
        return $this->config;
    }
}
