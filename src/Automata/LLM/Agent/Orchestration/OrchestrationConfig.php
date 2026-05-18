<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration;

use BlueFission\Automata\LLM\Agent\ToolDefinition;

class OrchestrationConfig
{
    public const SEQUENTIAL = 'sequential';
    public const FAN_OUT = 'fan_out';
    public const HIERARCHICAL = 'hierarchical';
    public const REFLEXIVE = 'reflexive';
    public const PIANO = 'piano';
    public const MERGE_COLLECT_CONFLICTS = 'collect_conflicts';
    public const MERGE_PREFER_LAST = 'prefer_last';

    protected array $config;

    /**
     * Create orchestration configuration.
     */
    public function __construct(array $config = [])
    {
        $this->config = ToolDefinition::mergeConfig([
            'pattern' => self::SEQUENTIAL,
            'workers' => [],
            'supervisor' => null,
            'producer' => null,
            'verifier' => null,
            'fallback' => null,
            'merge_policy' => self::MERGE_COLLECT_CONFLICTS,
            'confidence_threshold' => 0.75,
            'max_iterations' => 3,
            'model_tiers' => [],
            'patterns' => [],
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

    public function patterns(): array
    {
        return $this->config['patterns'] ?? [];
    }

    public function toArray(): array
    {
        return $this->config;
    }
}
