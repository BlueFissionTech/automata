<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationResult;

class PianoPattern extends AbstractPattern
{
    public function name(): string
    {
        return OrchestrationConfig::PIANO;
    }

    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult
    {
        $controller = $config->supervisor();
        $decision = $controller ? $this->invokeWorker($controller, $input, 'cognitive_controller', []) : [
            'name' => 'cognitive_controller',
            'status' => 'completed',
            'output' => $input,
            'confidence' => 1.0,
            'metadata' => [],
        ];

        $workerResults = [$decision];
        foreach ($config->workers() as $name => $worker) {
            $workerResults[] = $this->invokeWorker($worker, [
                'input' => $input,
                'controller_decision' => $decision['output'],
                'session' => $input['session'] ?? null,
                'state' => $input['state'] ?? null,
                'working_memory' => $input['working_memory'] ?? null,
            ], (string)$name, $workerResults);
        }

        $merged = $this->mergeWorkerResults($config, $workerResults);

        return new OrchestrationResult([
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
