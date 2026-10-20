<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Delegation\DelegationStatus;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationResult;

class PianoPattern extends AbstractPattern
{
    /** Identify cognitive-controller orchestration. */
    public function name(): string
    {
        return OrchestrationConfig::PIANO;
    }

    /** Dispatch only an admitted controller decision and retain child outcomes. */
    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult
    {
        $controller = $config->supervisor();
        $decision = $controller ? $this->invokeWorker($controller, $input, 'cognitive_controller', []) : [
            'name' => 'cognitive_controller',
            'status' => 'completed',
            'output' => $input,
            // The implicit controller has no measured quality evidence.
            'confidence' => null,
            'metadata' => [],
        ];

        $workerResults = [$decision];
        // A blocked or unresolved decision is a prerequisite, not an invitation
        // to run children. Accepted planning may proceed like hierarchical work.
        $selected = Arr::has([DelegationStatus::COMPLETED, DelegationStatus::ACCEPTED],
            $decision['status'], true) ? $config->workers() : [];
        foreach ($selected as $name => $worker) {
            $workerResults[] = $this->invokeWorker($worker, [
                'input' => $input,
                'controller_decision' => $decision['output'],
                'session' => $input['session'] ?? null,
                'state' => $input['state'] ?? null,
                'working_memory' => $input['working_memory'] ?? null,
            ], (string)$name, $workerResults);
        }

        $merged = $this->mergeWorkerResults($config, $workerResults);
        $children = Arr::make($workerResults)->slice(1)->toArray();

        return new OrchestrationResult([
            'status' => $this->aggregateWorkerStatus(Arr::count($children) > 0 ? $children : $workerResults),
            'pattern' => $this->name(),
            'output' => $merged['output'],
            'worker_results' => $workerResults,
            'conflicts' => $merged['conflicts'],
            'confidence' => $this->averageConfidence($workerResults),
            'metadata' => [
                'controller_decision' => $decision['output'],
            ],
        ]);
    }
}
