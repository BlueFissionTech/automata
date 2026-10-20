<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationStatus;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Func;
use BlueFission\Num;
use BlueFission\Automata\Support\RecordSnapshot;

abstract class AbstractPattern implements IOrchestrationPattern
{
    /**
     * Summarize independent execution evidence without manufacturing completion.
     *
     * Unknown or malformed explicit status remains unresolved, even beside a
     * successful peer or fallback. The original worker records are never changed.
     * Partial success, active work and terminal failure remain distinct; an
     * empty group has no outstanding work. This does not schedule or retry work.
     */
    protected function aggregateWorkerStatus(array $workerResults): string
    {
        $statuses = Arr::make($workerResults)->map(
            static fn (array $result): mixed => Arr::hasKey($result, 'status')
                ? $result['status'] : DelegationStatus::COMPLETED
        );
        $known = [
            DelegationStatus::COMPLETED, DelegationStatus::PARTIAL,
            DelegationStatus::PENDING, DelegationStatus::ACCEPTED, DelegationStatus::IN_PROGRESS,
            DelegationStatus::FAILED, DelegationStatus::REJECTED,
            DelegationStatus::CANCELLED, DelegationStatus::TIMED_OUT,
        ];
        // Strict membership also handles explicit null, booleans and future
        // status values without coercing them or losing their raw receipts.
        if ($statuses->filter(static fn (mixed $status): bool => !Arr::has($known, $status, true))->count() > 0) {
            return 'uncertain';
        }
        $completed = $statuses->filter(
            static fn (string $status): bool => $status === DelegationStatus::COMPLETED
        )->count();
        $partial = $statuses->filter(
            static fn (string $status): bool => $status === DelegationStatus::PARTIAL
        )->count();
        $active = $statuses->filter(static fn (string $status): bool => Arr::has([
            DelegationStatus::PENDING, DelegationStatus::ACCEPTED, DelegationStatus::IN_PROGRESS,
        ], $status, true))->count();
        $failed = $statuses->filter(static fn (string $status): bool => Arr::has([
            DelegationStatus::FAILED, DelegationStatus::REJECTED,
            DelegationStatus::CANCELLED, DelegationStatus::TIMED_OUT,
        ], $status, true))->count();

        if ($partial > 0 || ($completed > 0 && ($active > 0 || $failed > 0))) {
            return DelegationStatus::PARTIAL;
        }
        if ($active > 0) {
            return $failed > 0 ? DelegationStatus::PARTIAL : DelegationStatus::IN_PROGRESS;
        }
        return $failed > 0 ? DelegationStatus::FAILED : DelegationStatus::COMPLETED;
    }

    /**
     * Invoke a worker and retain only explicitly measured confidence.
     *
     * Successful execution does not imply certainty. Unknown confidence stays
     * null; supplied scores must be finite numeric ratios, never coerced strings.
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
            $result = ToolDefinition::mergeConfig([
                'name' => $name,
                'status' => 'completed',
                'confidence' => null,
                'metadata' => Arr::is($worker) ? ($worker['metadata'] ?? []) : [],
            ], $output);
            $result['confidence'] = $this->measuredConfidence($result['confidence']);
            return $result;
        }

        return [
            'name' => $name,
            'status' => 'completed',
            'output' => $output,
            'confidence' => $this->measuredConfidence(Arr::is($worker) ? ($worker['confidence'] ?? null) : null),
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
     * Average measured contributors only when every contribution is known.
     * A partial sample must not be presented as confidence for the whole result.
     */
    protected function averageConfidence(array $workerResults): ?float
    {
        $total = Num::make(0.0);
        foreach ($workerResults as $result) {
            $score = $this->measuredConfidence($result['confidence'] ?? null);
            if ($score === null) { return null; }
            $total->add($score);
        }

        if (Arr::count($workerResults) === 0) {
            return null;
        }

        return (float) $total->divide(Arr::count($workerResults))->round(4)->val();
    }

    /** Keep unknown separate from measured zero and reject invalid evidence. */
    protected function measuredConfidence(mixed $value): ?float
    {
        return $value === null ? null : RecordSnapshot::number($value, 'Worker confidence', 0.0, 1.0);
    }
}
