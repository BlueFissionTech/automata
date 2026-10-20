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

    /**
     * Revise rejected proposals within the iteration bound.
     *
     * Only a completed verifier's Boolean true certifies a proposal. A failed,
     * pending or uncertain worker stops the loop with its receipt intact; this
     * pattern cannot decide whether retrying an uncertain effect would be safe.
     */
    public function run(OrchestrationConfig $config, array $input = []): OrchestrationResult
    {
        $producer = $config->producer();
        $verifier = $config->verifier();
        $workerResults = [];
        $feedback = null;
        $output = null;
        $passed = false;
        $status = 'failed';
        $iterations = 0;
        $stoppedWorker = null;

        for ($iteration = 1; $iteration <= $config->maxIterations(); $iteration++) {
            ++$iterations;
            $outputResult = $this->invokeWorker($producer, [
                'input' => $input,
                'feedback' => $feedback,
                'iteration' => $iteration,
            ], 'producer', $workerResults);
            $workerResults[] = $outputResult;
            $output = $outputResult['output'];
            if ($outputResult['status'] !== 'completed') {
                $status = $outputResult['status'];
                $stoppedWorker = 'producer';
                break;
            }

            $verifyResult = $this->invokeWorker($verifier, [
                'input' => $input,
                'output' => $output,
                'iteration' => $iteration,
            ], 'verifier', $workerResults);
            $workerResults[] = $verifyResult;
            if ($verifyResult['status'] !== 'completed') {
                $status = $verifyResult['status'];
                $stoppedWorker = 'verifier';
                break;
            }
            $verification = Arr::is($verifyResult['output']) ? $verifyResult['output'] : [];
            $passed = ($verification['passed'] ?? false) === true;
            $feedback = $verification['feedback'] ?? null;

            if ($passed) {
                $status = 'completed';
                break;
            }
        }

        return new OrchestrationResult([
            'status' => $status,
            'pattern' => $this->name(),
            'output' => $output,
            'worker_results' => $workerResults,
            'iterations' => $iterations,
            'confidence' => $this->averageConfidence($workerResults),
            'metadata' => ['passed' => $passed, 'feedback' => $feedback, 'stopped_worker' => $stoppedWorker],
        ]);
    }
}
