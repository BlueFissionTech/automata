<?php

namespace BlueFission\Automata\LLM\Agent\Telemetry;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;

class CpctReport
{
    public static function build(array $traces, array|CpctPricing $pricing = [], array $config = []): array
    {
        $pricing = $pricing instanceof CpctPricing ? $pricing : new CpctPricing($pricing);
        $taskRows = [];
        $costs = [];
        $cacheHits = 0;
        $cacheWrites = 0;
        $cacheSavings = 0.0;
        $batchable = 0;
        $batched = 0;
        $tierSavings = 0.0;
        $tierCandidates = 0;
        $tierSloMatches = 0;
        $statusCounts = [];

        foreach ($traces as $trace) {
            $trace = $trace instanceof TaskTrace ? $trace : TaskTrace::fromArray($trace);
            $totals = $trace->totals($pricing);
            $status = $trace->outcomeStatus();
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            foreach ($trace->spans() as $span) {
                $row = $span->toArray();
                $cacheHits += (int)($row['cache_hit_tokens'] ?? 0);
                $cacheWrites += (int)($row['cache_write_tokens'] ?? 0);
                $cacheSavings += $pricing->savingsForCacheHits($row);

                if (($row['batchable'] ?? false) === true) {
                    $batchable++;
                    if (($row['batch_processed'] ?? false) === true || (int)($row['batch_tokens'] ?? 0) > 0) {
                        $batched++;
                    }
                }

                if (isset($row['candidate_estimated_cost'])) {
                    $tierCandidates++;
                    $current = $row['estimated_cost'] ?? $pricing->costForSpan($row);
                    $candidate = (float)$row['candidate_estimated_cost'];
                    if (($row['candidate_met_slo'] ?? false) === true) {
                        $tierSloMatches++;
                        $tierSavings += max(0.0, (float)$current - $candidate);
                    }
                }
            }

            $taskRows[] = [
                'task_id' => $trace->taskId(),
                'outcome_status' => $status,
                'totals' => $totals,
                'over_budget' => isset($config['target_cost']) && $totals['total_cost'] > (float)$config['target_cost'],
            ];

            if ($status === 'completed') {
                $costs[] = $totals['total_cost'];
            }
        }

        sort($costs);

        $report = [
            'task_count' => count($taskRows),
            'status_counts' => $statusCounts,
            'cpct_distribution' => [
                'p50' => self::percentile($costs, 50),
                'p90' => self::percentile($costs, 90),
                'p99' => self::percentile($costs, 99),
            ],
            'cache_roi' => [
                'cache_hit_tokens' => $cacheHits,
                'cache_write_tokens' => $cacheWrites,
                'hit_to_write_ratio' => $cacheWrites > 0 ? round($cacheHits / $cacheWrites, 4) : null,
                'estimated_savings' => round($cacheSavings, 8),
            ],
            'batch_utilization' => [
                'batchable_spans' => $batchable,
                'batched_spans' => $batched,
                'utilization' => $batchable > 0 ? round($batched / $batchable, 4) : null,
            ],
            'tier_routing' => [
                'candidate_spans' => $tierCandidates,
                'slo_match_spans' => $tierSloMatches,
                'slo_match_rate' => $tierCandidates > 0 ? round($tierSloMatches / $tierCandidates, 4) : null,
                'estimated_savings' => round($tierSavings, 8),
            ],
            'tasks' => $taskRows,
        ];

        return Dev::apply('automata.llm.agent.telemetry.cpct_report', $report);
    }

    protected static function percentile(array $values, int $percentile): float
    {
        if (!$values) {
            return 0.0;
        }

        $index = (($percentile / 100) * (count($values) - 1));
        $lower = (int)floor($index);
        $upper = (int)ceil($index);
        if ($lower === $upper) {
            return round((float)$values[$lower], 8);
        }

        $weight = $index - $lower;
        return round(((float)$values[$lower] * (1 - $weight)) + ((float)$values[$upper] * $weight), 8);
    }
}
