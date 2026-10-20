<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationResult;
use BlueFission\Arr;

class SequentialPattern extends AbstractPattern
{
    public function name(): string
    {
        return OrchestrationConfig::SEQUENTIAL;
    }

    /**
     * Execute ordered prerequisites until one does not complete.
     *
     * Keep the observed receipt in context, but never pass it to dependents as
     * successful evidence. Pending/uncertain work requires host reconciliation,
     * not an implicit retry of this chain.
     */
    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult
    {
        $context = $input;
        $workerResults = [];
        $status = 'completed';
        $stoppedWorker = null;

        foreach ($config->workers() as $name => $worker) {
            $result = $this->invokeWorker($worker, $context, (string)$name, $workerResults);
            $workerResults[] = $result;
            $context[(string)$name] = $result['output'];
            if ($result['status'] !== 'completed') {
                $status = $result['status'];
                $stoppedWorker = (string) $name;
                break;
            }
        }

        return new OrchestrationResult([
            'status' => $status,
            'pattern' => $this->name(),
            'output' => $context,
            'worker_results' => $workerResults,
            'confidence' => $this->averageConfidence($workerResults),
            'metadata' => [
                'stopped_worker' => $stoppedWorker,
                'skipped_workers' => Arr::make($config->workers())->keys()
                    ->slice(Arr::count($workerResults))->val(),
            ],
        ]);
    }
}
