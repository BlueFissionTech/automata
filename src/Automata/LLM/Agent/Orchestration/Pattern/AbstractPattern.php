<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Func;

abstract class AbstractPattern implements IOrchestrationPattern
{
    /**
     * Invoke a worker callable and normalize its result.
     */
    protected function invokeWorker(mixed $worker, array $context, string $name, array $priorResults): array
    {
        $handler = Arr::is($worker) && Arr::hasKey($worker, 'handler') ? $worker['handler'] : $worker;
        if (!Func::isCallable($handler)) {
            return [
                'name' => $name,
                'status' => 'failed',
                'output' => null,
                'confidence' => 0.0,
                'metadata' => ['error' => 'worker_not_callable'],
            ];
        }

        $output = $handler($context, $priorResults);
        if (Arr::is($output) && Arr::hasKey($output, 'output')) {
            return ToolDefinition::mergeConfig([
                'name' => $name,
                'status' => 'completed',
                'confidence' => 1.0,
                'metadata' => Arr::is($worker) ? ($worker['metadata'] ?? []) : [],
            ], $output);
        }

        return [
            'name' => $name,
            'status' => 'completed',
            'output' => $output,
            'confidence' => Arr::is($worker) && Arr::hasKey($worker, 'confidence') ? (float)$worker['confidence'] : 1.0,
            'metadata' => Arr::is($worker) ? ($worker['metadata'] ?? []) : [],
        ];
    }

    /**
     * Merge worker outputs and collect conflicts according to config.
     */
    protected function mergeWorkerResults(OrchestrationConfig $config, array $workerResults): array
    {
        $output = [];
        $conflicts = [];

        foreach ($workerResults as $result) {
            $value = $result['output'] ?? null;
            if (($result['metadata']['black_box'] ?? false) === true) {
                $output[$result['name']] = $value;
                continue;
            }

            if (!Arr::is($value)) {
                $output[$result['name']] = $value;
                continue;
            }

            foreach ($value as $key => $item) {
                if (Arr::hasKey($output, $key) && $output[$key] !== $item) {
                    $conflicts[$key][] = [
                        'worker' => $result['name'],
                        'value' => $item,
                        'existing' => $output[$key],
                    ];

                    if ($config->mergePolicy() === OrchestrationConfig::MERGE_PREFER_LAST) {
                        $output[$key] = $item;
                    } elseif ($config->mergePolicy() === OrchestrationConfig::MERGE_COLLECT_CONFLICTS) {
                        $output[$key] = [$output[$key], $item];
                    }
                    continue;
                }

                $output[$key] = $item;
            }
        }

        return ['output' => $output, 'conflicts' => $conflicts];
    }

    /**
     * Calculate average worker confidence.
     */
    protected function averageConfidence(array $workerResults): ?float
    {
        $scores = [];
        foreach ($workerResults as $result) {
            if (Arr::hasKey($result, 'confidence')) {
                $scores[] = (float)$result['confidence'];
            }
        }

        if (!$scores) {
            return null;
        }

        return round(array_sum($scores) / Arr::make($scores)->count(), 4);
    }
}
