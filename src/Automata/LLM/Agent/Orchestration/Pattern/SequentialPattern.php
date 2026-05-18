<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationResult;

class SequentialPattern extends AbstractPattern
{
    public function name(): string
    {
        return OrchestrationConfig::SEQUENTIAL;
    }

    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult
    {
        $context = $input;
        $workerResults = [];

        foreach ($config->workers() as $name => $worker) {
            $result = $this->invokeWorker($worker, $context, (string)$name, $workerResults);
            $workerResults[] = $result;
            $context[(string)$name] = $result['output'];
        }

        return new OrchestrationResult([
            'pattern' => $this->name(),
            'output' => $context,
            'worker_results' => $workerResults,
            'confidence' => $this->averageConfidence($workerResults),
        ]);
    }
}
