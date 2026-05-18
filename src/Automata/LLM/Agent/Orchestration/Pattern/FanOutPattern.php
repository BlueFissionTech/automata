<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationResult;

class FanOutPattern extends AbstractPattern
{
    public function name(): string
    {
        return OrchestrationConfig::FAN_OUT;
    }

    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult
    {
        $workerResults = [];
        foreach ($config->workers() as $name => $worker) {
            $workerResults[] = $this->invokeWorker($worker, $input, (string)$name, $workerResults);
        }

        $merged = $this->mergeWorkerResults($config, $workerResults);

        return new OrchestrationResult([
            'pattern' => $this->name(),
            'output' => $merged['output'],
            'worker_results' => $workerResults,
            'conflicts' => $merged['conflicts'],
            'confidence' => $this->averageConfidence($workerResults),
        ]);
    }
}
