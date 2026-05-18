<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration\Pattern;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationResult;

class ReflexivePattern extends AbstractPattern
{
    public function name(): string
    {
        return OrchestrationConfig::REFLEXIVE;
    }

    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult
    {
        $producer = $config->producer();
        $verifier = $config->verifier();
        $workerResults = [];
        $feedback = null;
        $output = null;
        $passed = false;

        for ($iteration = 1; $iteration <= $config->maxIterations(); $iteration++) {
            $outputResult = $this->invokeWorker($producer, [
                'input' => $input,
                'feedback' => $feedback,
                'iteration' => $iteration,
            ], 'producer', $workerResults);
            $workerResults[] = $outputResult;
            $output = $outputResult['output'];

            $verifyResult = $this->invokeWorker($verifier, [
                'input' => $input,
                'output' => $output,
                'iteration' => $iteration,
            ], 'verifier', $workerResults);
            $workerResults[] = $verifyResult;
            $passed = (bool)($verifyResult['output']['passed'] ?? false);
            $feedback = $verifyResult['output']['feedback'] ?? null;

            if ($passed) {
                break;
            }
        }

        return new OrchestrationResult([
            'status' => $passed ? 'completed' : 'failed',
            'pattern' => $this->name(),
            'output' => $output,
            'worker_results' => $workerResults,
            'iterations' => (int)ceil(Arr::make($workerResults)->count() / 2),
            'confidence' => $this->averageConfidence($workerResults),
            'metadata' => ['passed' => $passed, 'feedback' => $feedback],
        ]);
    }
}
