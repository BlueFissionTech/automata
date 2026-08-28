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
        $selected = in_array($planStatus, [DelegationStatus::COMPLETED, DelegationStatus::ACCEPTED], true)
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

        $mergeable = [];
        foreach ($workerResults as $index => $result) {
            if ($index > 0) {
                $mergeable[] = $result;
            }
        }
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
        $statuses = array_map(
            static fn (array $result): string => (string)($result['status'] ?? DelegationStatus::COMPLETED),
            array_slice($workerResults, 1)
        );
        if (Arr::count($statuses) === 0) {
            return (string)($workerResults[0]['status'] ?? DelegationStatus::COMPLETED);
        }

        $completed = array_filter(
            $statuses,
            static fn (string $status): bool => $status === DelegationStatus::COMPLETED
        );
        $partial = array_filter(
            $statuses,
            static fn (string $status): bool => $status === DelegationStatus::PARTIAL
        );
        $active = array_filter($statuses, static fn (string $status): bool => in_array($status, [
            DelegationStatus::PENDING,
            DelegationStatus::ACCEPTED,
            DelegationStatus::IN_PROGRESS,
        ], true));
        $failed = array_filter($statuses, static fn (string $status): bool => in_array($status, [
            DelegationStatus::REJECTED,
            DelegationStatus::FAILED,
            DelegationStatus::CANCELLED,
            DelegationStatus::TIMED_OUT,
        ], true));

        if (Arr::count($partial) > 0 || (Arr::count($failed) > 0 && Arr::count($completed) > 0)) {
            return DelegationStatus::PARTIAL;
        }

        if (Arr::count($active) > 0) {
            return Arr::count($completed) > 0 || Arr::count($failed) > 0
                ? DelegationStatus::PARTIAL
                : DelegationStatus::IN_PROGRESS;
        }

        return Arr::count($failed) > 0 ? DelegationStatus::FAILED : DelegationStatus::COMPLETED;
    }
}
