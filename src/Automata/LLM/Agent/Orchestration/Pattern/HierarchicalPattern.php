<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationResult;

class HierarchicalPattern extends AbstractPattern
{
    public function name(): string
    {
        return OrchestrationConfig::HIERARCHICAL;
    }

    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult
    {
        $supervisor = $config->supervisor();
        $plan = $supervisor ? $this->invokeWorker($supervisor, $input, 'supervisor', []) : [
            'output' => ['workers' => Arr::keys($config->workers())],
            'confidence' => 1.0,
        ];

        $selected = $plan['output']['workers'] ?? Arr::keys($config->workers());
        $workerResults = [$plan + ['name' => 'supervisor']];
        foreach ($selected as $name) {
            if (!Arr::hasKey($config->workers(), $name)) {
                continue;
            }

            $result = $this->invokeWorker($config->workers()[$name], $input, (string)$name, $workerResults);
            $workerResults[] = $result;
            if (($result['confidence'] ?? 1.0) < $config->confidenceThreshold() && $config->fallback()) {
                $workerResults[] = $this->invokeWorker($config->fallback(), [
                    'input' => $input,
                    'failed_worker' => $result,
                ], 'fallback', $workerResults);
            }
        }

        $mergeable = [];
        foreach ($workerResults as $index => $result) {
            if ($index > 0) {
                $mergeable[] = $result;
            }
        }
        $merged = $this->mergeWorkerResults($config, $mergeable);

        return new OrchestrationResult([
            'pattern' => $this->name(),
            'output' => $merged['output'],
            'worker_results' => $workerResults,
            'conflicts' => $merged['conflicts'],
            'confidence' => $this->averageConfidence($workerResults),
            'metadata' => ['plan' => $plan['output']],
        ]);
    }
}
