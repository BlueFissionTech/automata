<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationStatus;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationResult;

class HierarchicalPattern extends AbstractPattern
{
    /** Identify controller-selected peer scheduling. */
    public function name(): string
    {
        return OrchestrationConfig::HIERARCHICAL;
    }

    /** Gate dispatch on the plan, then preserve every selected worker's evidence. */
    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult
    {
        $supervisor = $config->supervisor();
        $plan = $supervisor ? $this->invokeWorker($supervisor, $input, 'supervisor', []) : [
            'output' => ['workers' => Arr::keys($config->workers())],
            'confidence' => null,
        ];

        // An explicit null or unknown planning outcome cannot authorize dispatch.
        $planStatus = Arr::hasKey($plan, 'status') ? $plan['status'] : DelegationStatus::COMPLETED;
        $selected = Arr::has([DelegationStatus::COMPLETED, DelegationStatus::ACCEPTED], $planStatus, true)
            ? ($plan['output']['workers'] ?? Arr::keys($config->workers()))
            : [];
        $workerResults = [$plan + ['name' => 'supervisor']];
        foreach ($selected as $name) {
            if (!Arr::hasKey($config->workers(), $name)) {
                continue;
            }

            $result = $this->invokeWorker($config->workers()[$name], $input, (string)$name, $workerResults);
            $workerResults[] = $result;
            // Lack of evidence is not a low score. Only measured confidence
            // participates in this threshold; status and authority are separate.
            if ($result['confidence'] !== null && $result['confidence'] < $config->confidenceThreshold() && $config->fallback()) {
                $workerResults[] = $this->invokeWorker($config->fallback(), [
                    'input' => $input,
                    'failed_worker' => $result,
                ], 'fallback', $workerResults);
            }
        }

        $mergeable = Arr::make($workerResults)->slice(1)->toArray();
        $merged = $this->mergeWorkerResults($config, $mergeable);

        return new OrchestrationResult([
            'status' => $this->aggregateStatus($workerResults),
            'pattern' => $this->name(),
            'output' => $merged['output'],
            'worker_results' => $workerResults,
            'conflicts' => $merged['conflicts'],
            'confidence' => $this->averageConfidence($workerResults),
            'metadata' => ['plan' => $plan['output'] ?? null],
        ]);
    }

    /**
     * Aggregate children once dispatch is admitted; otherwise retain the plan's
     * unresolved evidence. Accepted planning is not a pending child execution.
     */
    protected function aggregateStatus(array $workerResults): string
    {
        $children = Arr::make($workerResults)->slice(1)->toArray();
        return $this->aggregateWorkerStatus(Arr::count($children) > 0 ? $children : $workerResults);
    }
}
