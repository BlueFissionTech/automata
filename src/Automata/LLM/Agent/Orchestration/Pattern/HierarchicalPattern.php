<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationStatus;
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

        $planStatus = (string)($plan['status'] ?? DelegationStatus::COMPLETED);
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
            if (($result['confidence'] ?? 1.0) < $config->confidenceThreshold() && $config->fallback()) {
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

    protected function aggregateStatus(array $workerResults): string
    {
        $statuses = Arr::make($workerResults)
            ->slice(1)
            ->map(static fn (array $result): string => (string)($result['status'] ?? DelegationStatus::COMPLETED))
            ->toArray();
        if (Arr::count($statuses) === 0) {
            return (string)($workerResults[0]['status'] ?? DelegationStatus::COMPLETED);
        }

        $statuses = Arr::make($statuses);
        $completed = $statuses->filter(
            static fn (string $status): bool => $status === DelegationStatus::COMPLETED
        );
        $partial = $statuses->filter(
            static fn (string $status): bool => $status === DelegationStatus::PARTIAL
        );
        $active = $statuses->filter(static fn (string $status): bool => Arr::has([
            DelegationStatus::PENDING,
            DelegationStatus::ACCEPTED,
            DelegationStatus::IN_PROGRESS,
        ], $status, true));
        $failed = $statuses->filter(static fn (string $status): bool => Arr::has([
            DelegationStatus::REJECTED,
            DelegationStatus::FAILED,
            DelegationStatus::CANCELLED,
            DelegationStatus::TIMED_OUT,
        ], $status, true));

        if ($partial->count() > 0 || ($failed->count() > 0 && $completed->count() > 0)) {
            return DelegationStatus::PARTIAL;
        }

        if ($active->count() > 0) {
            return $completed->count() > 0 || $failed->count() > 0
                ? DelegationStatus::PARTIAL
                : DelegationStatus::IN_PROGRESS;
        }

        return $failed->count() > 0 ? DelegationStatus::FAILED : DelegationStatus::COMPLETED;
    }
}
