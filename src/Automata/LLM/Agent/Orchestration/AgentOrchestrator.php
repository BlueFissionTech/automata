<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration;

use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\DevElation as Dev;
use Throwable;

class AgentOrchestrator
{
    protected OrchestrationConfig $config;

    public function __construct(OrchestrationConfig|array $config = [])
    {
        $this->config = $config instanceof OrchestrationConfig ? $config : new OrchestrationConfig($config);
    }

    public function run(array $input = [], ?TaskTrace $trace = null): OrchestrationResult
    {
        $span = $trace ? $trace->startSpan(TaskTraceSpan::KIND_ORCHESTRATION, $this->config->pattern(), [
            'config' => $this->safeConfig(),
        ]) : null;

        try {
            $result = match ($this->config->pattern()) {
                OrchestrationConfig::FAN_OUT => $this->runFanOut($input),
                OrchestrationConfig::HIERARCHICAL => $this->runHierarchical($input),
                OrchestrationConfig::REFLEXIVE => $this->runReflexive($input),
                default => $this->runSequential($input),
            };
        } catch (Throwable $exception) {
            $result = new OrchestrationResult([
                'status' => 'failed',
                'pattern' => $this->config->pattern(),
                'metadata' => ['error' => $exception->getMessage()],
            ]);
        }

        if ($span && $trace) {
            $trace->addSpan($span->finish($result->status(), [
                'outcome_status' => $result->status(),
                'metadata' => [
                    'result' => $result->toArray(),
                ],
            ]));
        }

        Dev::do('automata.llm.agent.orchestration.completed', $result->toArray());

        return $result;
    }

    protected function runSequential(array $input): OrchestrationResult
    {
        $context = $input;
        $workerResults = [];

        foreach ($this->config->workers() as $name => $worker) {
            $result = $this->invokeWorker($worker, $context, (string)$name, $workerResults);
            $workerResults[] = $result;
            $context[(string)$name] = $result['output'];
        }

        return new OrchestrationResult([
            'pattern' => OrchestrationConfig::SEQUENTIAL,
            'output' => $context,
            'worker_results' => $workerResults,
            'confidence' => $this->averageConfidence($workerResults),
        ]);
    }

    protected function runFanOut(array $input): OrchestrationResult
    {
        $workerResults = [];
        foreach ($this->config->workers() as $name => $worker) {
            $workerResults[] = $this->invokeWorker($worker, $input, (string)$name, $workerResults);
        }

        $merged = $this->mergeWorkerResults($workerResults);

        return new OrchestrationResult([
            'pattern' => OrchestrationConfig::FAN_OUT,
            'output' => $merged['output'],
            'worker_results' => $workerResults,
            'conflicts' => $merged['conflicts'],
            'confidence' => $this->averageConfidence($workerResults),
        ]);
    }

    protected function runHierarchical(array $input): OrchestrationResult
    {
        $supervisor = $this->config->supervisor();
        $plan = $supervisor ? $this->invokeWorker($supervisor, $input, 'supervisor', []) : [
            'output' => ['workers' => array_keys($this->config->workers())],
            'confidence' => 1.0,
        ];

        $selected = $plan['output']['workers'] ?? array_keys($this->config->workers());
        $workerResults = [$plan + ['name' => 'supervisor']];
        foreach ($selected as $name) {
            if (!isset($this->config->workers()[$name])) {
                continue;
            }

            $result = $this->invokeWorker($this->config->workers()[$name], $input, (string)$name, $workerResults);
            $workerResults[] = $result;
            if (($result['confidence'] ?? 1.0) < $this->config->confidenceThreshold() && $this->config->fallback()) {
                $workerResults[] = $this->invokeWorker($this->config->fallback(), [
                    'input' => $input,
                    'failed_worker' => $result,
                ], 'fallback', $workerResults);
            }
        }

        $merged = $this->mergeWorkerResults(array_slice($workerResults, 1));

        return new OrchestrationResult([
            'pattern' => OrchestrationConfig::HIERARCHICAL,
            'output' => $merged['output'],
            'worker_results' => $workerResults,
            'conflicts' => $merged['conflicts'],
            'confidence' => $this->averageConfidence($workerResults),
            'metadata' => ['plan' => $plan['output']],
        ]);
    }

    protected function runReflexive(array $input): OrchestrationResult
    {
        $producer = $this->config->producer();
        $verifier = $this->config->verifier();
        $workerResults = [];
        $feedback = null;
        $output = null;
        $passed = false;

        for ($iteration = 1; $iteration <= $this->config->maxIterations(); $iteration++) {
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
            'pattern' => OrchestrationConfig::REFLEXIVE,
            'output' => $output,
            'worker_results' => $workerResults,
            'iterations' => (int)ceil(count($workerResults) / 2),
            'confidence' => $this->averageConfidence($workerResults),
            'metadata' => ['passed' => $passed, 'feedback' => $feedback],
        ]);
    }

    protected function invokeWorker(mixed $worker, array $context, string $name, array $priorResults): array
    {
        $handler = is_array($worker) && isset($worker['handler']) ? $worker['handler'] : $worker;
        if (!is_callable($handler)) {
            return [
                'name' => $name,
                'status' => 'failed',
                'output' => null,
                'confidence' => 0.0,
                'metadata' => ['error' => 'worker_not_callable'],
            ];
        }

        $output = $handler($context, $priorResults);
        if (is_array($output) && array_key_exists('output', $output)) {
            return array_replace_recursive([
                'name' => $name,
                'status' => 'completed',
                'confidence' => 1.0,
                'metadata' => [],
            ], $output);
        }

        return [
            'name' => $name,
            'status' => 'completed',
            'output' => $output,
            'confidence' => is_array($worker) && isset($worker['confidence']) ? (float)$worker['confidence'] : 1.0,
            'metadata' => is_array($worker) ? ($worker['metadata'] ?? []) : [],
        ];
    }

    protected function mergeWorkerResults(array $workerResults): array
    {
        $output = [];
        $conflicts = [];

        foreach ($workerResults as $result) {
            $value = $result['output'] ?? null;
            if (!is_array($value)) {
                $output[$result['name']] = $value;
                continue;
            }

            foreach ($value as $key => $item) {
                if (array_key_exists($key, $output) && $output[$key] !== $item) {
                    $conflicts[$key][] = [
                        'worker' => $result['name'],
                        'value' => $item,
                        'existing' => $output[$key],
                    ];

                    if ($this->config->mergePolicy() === 'prefer_last') {
                        $output[$key] = $item;
                    } elseif ($this->config->mergePolicy() === 'collect_conflicts') {
                        $output[$key] = [$output[$key], $item];
                    }
                    continue;
                }

                $output[$key] = $item;
            }
        }

        return ['output' => $output, 'conflicts' => $conflicts];
    }

    protected function averageConfidence(array $workerResults): ?float
    {
        $scores = array_filter(array_map(
            fn (array $result): ?float => isset($result['confidence']) ? (float)$result['confidence'] : null,
            $workerResults
        ), fn ($score): bool => $score !== null);

        if (!$scores) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 4);
    }

    protected function safeConfig(): array
    {
        $config = $this->config->toArray();
        foreach (['workers', 'supervisor', 'producer', 'verifier', 'fallback'] as $key) {
            if (isset($config[$key])) {
                $config[$key] = is_array($config[$key]) ? array_keys($config[$key]) : gettype($config[$key]);
            }
        }

        return $config;
    }
}
