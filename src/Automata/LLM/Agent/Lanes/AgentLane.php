<?php

namespace BlueFission\Automata\LLM\Agent\Lanes;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Obj;

class AgentLane extends Obj
{
    public const SEMANTIC = 'semantic';
    public const OPERATIONAL = 'operational';
    public const EXECUTION = 'execution';

    public function __construct(array $config = [])
    {
        parent::__construct();
        $this->replaceFields(ToolDefinition::mergeConfig($this->defaults(), $config));
    }

    public static function semantic(): self
    {
        return new self([
            'id' => self::SEMANTIC,
            'label' => 'Semantic',
            'purpose' => 'Intent, meaning, context, and reasoning shape before action.',
            'responsibilities' => ['intent', 'context', 'semantic_map', 'ambiguity'],
            'pressure_signals' => [
                'context_load',
                'ambiguity',
                'semantic_drift',
                'unknown_terms',
                'mission_scope_gap',
                'source_map_gap',
                'durable_memory_gap',
            ],
            'recommendations' => [
                LanePressureManager::LEVEL_LOW => 'Keep normal reasoning context and continue.',
                LanePressureManager::LEVEL_MEDIUM => 'Summarize context, refresh the source map, and narrow the active evidence set.',
                LanePressureManager::LEVEL_HIGH => 'Pause execution, clarify intent, and rebuild the durable task frame before adding more context.',
                LanePressureManager::LEVEL_CRITICAL => 'Stop expansion and require an explicit spec, source map, or smaller semantic frame.',
            ],
        ]);
    }

    public static function operational(): self
    {
        return new self([
            'id' => self::OPERATIONAL,
            'label' => 'Operational',
            'purpose' => 'Policies, runbooks, permissions, budgets, and coordination rules.',
            'responsibilities' => ['policy', 'permissions', 'runbook', 'coordination', 'budget'],
            'pressure_signals' => [
                'policy_conflict',
                'approval_pending',
                'budget_pressure',
                'coordination_gap',
                'runbook_gap',
                'milestone_gap',
                'audit_log_gap',
                'governance_sync_gap',
            ],
            'recommendations' => [
                LanePressureManager::LEVEL_LOW => 'Keep normal policy checks and continue.',
                LanePressureManager::LEVEL_MEDIUM => 'Refresh the runbook, confirm permissions, and record the next checkpoint.',
                LanePressureManager::LEVEL_HIGH => 'Defer mutations until runbook, milestone, approval, or audit-log gaps are resolved.',
                LanePressureManager::LEVEL_CRITICAL => 'Block execution and route to human review or a new operating decision with an audit trail.',
            ],
        ]);
    }

    public static function execution(): self
    {
        return new self([
            'id' => self::EXECUTION,
            'label' => 'Execution',
            'purpose' => 'Tools, sandbox work, file changes, tests, and concrete runtime effects.',
            'responsibilities' => ['tool_use', 'filesystem', 'tests', 'runtime', 'subtasks'],
            'pressure_signals' => [
                'tool_failures',
                'test_failures',
                'mutation_risk',
                'runtime_load',
                'verification_gap',
                'observability_gap',
                'isolation_gap',
                'repair_loop_stall',
                'rollback_gap',
            ],
            'recommendations' => [
                LanePressureManager::LEVEL_LOW => 'Keep normal tool and test cadence.',
                LanePressureManager::LEVEL_MEDIUM => 'Run focused checks, expose relevant logs, and split large actions into smaller tool calls.',
                LanePressureManager::LEVEL_HIGH => 'Reduce concurrency, isolate failing tools, and validate a smaller change set with observable evidence.',
                LanePressureManager::LEVEL_CRITICAL => 'Stop further mutations until the execution failure, verification gap, or sandbox risk is diagnosed.',
            ],
        ]);
    }

    public static function standard(): array
    {
        return [
            self::SEMANTIC => self::semantic(),
            self::OPERATIONAL => self::operational(),
            self::EXECUTION => self::execution(),
        ];
    }

    public function id(): string
    {
        return (string)$this->field('id');
    }

    public function label(): string
    {
        return (string)$this->field('label');
    }

    public function purpose(): string
    {
        return (string)$this->field('purpose');
    }

    public function responsibilities(): array
    {
        return Arr::make($this->field('responsibilities') ?? [])->toArray();
    }

    public function pressureSignals(): array
    {
        return Arr::make($this->field('pressure_signals') ?? [])->toArray();
    }

    public function recommendation(string $level): string
    {
        $recommendations = Arr::make($this->field('recommendations') ?? [])->toArray();

        return (string)($recommendations[$level] ?? $recommendations[LanePressureManager::LEVEL_LOW] ?? '');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'label' => $this->label(),
            'purpose' => $this->purpose(),
            'responsibilities' => $this->responsibilities(),
            'pressure_signals' => $this->pressureSignals(),
            'recommendations' => Arr::make($this->field('recommendations') ?? [])->toArray(),
        ];
    }

    protected function defaults(): array
    {
        return [
            'id' => '',
            'label' => '',
            'purpose' => '',
            'responsibilities' => [],
            'pressure_signals' => [],
            'recommendations' => [],
        ];
    }

    protected function replaceFields(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->_data[$key] = $value;
        }
    }
}
