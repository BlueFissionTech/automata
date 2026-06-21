<?php

namespace BlueFission\Automata\LLM\Agent\Lanes;

use BlueFission\Num;

class LanePressureProfile
{
    /**
     * Convert long-horizon task readiness into lane metrics.
     */
    public static function longHorizonTask(array $readiness = []): array
    {
        return [
            AgentLane::SEMANTIC => [
                'mission_scope_gap' => self::gap($readiness, 'spec', 0.9, 1.1),
                'source_map_gap' => self::gap($readiness, 'source_map', 0.85, 1.1),
                'durable_memory_gap' => self::gap($readiness, 'durable_memory', 0.8, 1.0),
                'context_load' => self::direct($readiness, 'context_load'),
            ],
            AgentLane::OPERATIONAL => [
                'runbook_gap' => self::gap($readiness, 'runbook', 0.85, 1.1),
                'milestone_gap' => self::gap($readiness, 'milestones', 0.8, 1.0),
                'audit_log_gap' => self::gap($readiness, 'audit_log', 0.75, 0.9),
                'governance_sync_gap' => self::gap($readiness, 'local_governance', 0.7, 0.9),
                'policy_conflict' => self::direct($readiness, 'policy_conflict'),
            ],
            AgentLane::EXECUTION => [
                'verification_gap' => self::gap($readiness, 'verification', 0.9, 1.2),
                'observability_gap' => self::gap($readiness, 'observability', 0.75, 0.9),
                'isolation_gap' => self::gap($readiness, 'isolated_workspace', 0.8, 1.0),
                'repair_loop_stall' => self::gap($readiness, 'repair_loop', 0.75, 0.9),
                'rollback_gap' => self::gap($readiness, 'rollback_plan', 0.65, 0.8),
                'tool_failures' => self::direct($readiness, 'tool_failures'),
            ],
        ];
    }

    protected static function gap(array $readiness, string $key, float $defaultPressure, float $weight): array
    {
        return [
            'value' => self::pressure($readiness[$key] ?? null, $defaultPressure, true),
            'weight' => $weight,
        ];
    }

    protected static function direct(array $readiness, string $key): array
    {
        return [
            'value' => self::pressure($readiness[$key] ?? 0, 0.0, false),
            'weight' => 1.0,
        ];
    }

    protected static function pressure(mixed $value, float $defaultPressure, bool $invertReadiness): float
    {
        if ($value === null) {
            return $defaultPressure;
        }

        if ($value === true) {
            return $invertReadiness ? 0.0 : 1.0;
        }

        if ($value === false) {
            return $invertReadiness ? $defaultPressure : 0.0;
        }

        if (!Num::is($value)) {
            return $invertReadiness ? 0.0 : 0.0;
        }

        $normalized = Num::max(0.0, Num::min((float)$value, 1.0));

        return $invertReadiness ? Num::round(1.0 - $normalized, 4) : $normalized;
    }
}
