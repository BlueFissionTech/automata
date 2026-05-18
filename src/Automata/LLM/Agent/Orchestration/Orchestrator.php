<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Orchestration\Pattern\FanOutPattern;
use BlueFission\Automata\LLM\Agent\Orchestration\Pattern\HierarchicalPattern;
use BlueFission\Automata\LLM\Agent\Orchestration\Pattern\IOrchestrationPattern;
use BlueFission\Automata\LLM\Agent\Orchestration\Pattern\PianoPattern;
use BlueFission\Automata\LLM\Agent\Orchestration\Pattern\ReflexivePattern;
use BlueFission\Automata\LLM\Agent\Orchestration\Pattern\SequentialPattern;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\DevElation as Dev;
use Throwable;

class Orchestrator
{
    protected OrchestrationConfig $config;

    /** @var array<string,IOrchestrationPattern> */
    protected array $patterns = [];

    /**
     * Create an orchestrator with injectable orchestration patterns.
     */
    public function __construct(OrchestrationConfig|array $config = [], array $patterns = [])
    {
        $this->config = $config instanceof OrchestrationConfig ? $config : new OrchestrationConfig($config);
        foreach ($this->defaultPatterns() as $pattern) {
            $this->registerPattern($pattern);
        }
        foreach ($patterns as $pattern) {
            $this->registerPattern($pattern);
        }
        foreach ($this->config->patterns() as $pattern) {
            $this->registerPattern($pattern);
        }
    }

    /**
     * Register or replace a pattern implementation.
     */
    public function registerPattern(IOrchestrationPattern $pattern): self
    {
        $this->patterns[$pattern->name()] = $pattern;
        return $this;
    }

    /**
     * Run the configured orchestration pattern.
     */
    public function run(array $input = [], ?TaskTrace $trace = null): OrchestrationResult
    {
        $pattern = $this->pattern($this->config->pattern());
        $span = $trace ? $trace->startSpan(TaskTraceSpan::KIND_ORCHESTRATION, $pattern->name(), [
            'config' => $this->safeConfig(),
        ]) : null;

        try {
            $result = $pattern->run($this->config, $input);
        } catch (Throwable $exception) {
            $result = new OrchestrationResult([
                'status' => 'failed',
                'pattern' => $pattern->name(),
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

    /**
     * Resolve a pattern by name, falling back to sequential.
     */
    protected function pattern(string $name): IOrchestrationPattern
    {
        return $this->patterns[$name] ?? $this->patterns[OrchestrationConfig::SEQUENTIAL];
    }

    /**
     * Return built-in orchestration patterns.
     */
    protected function defaultPatterns(): array
    {
        return [
            new SequentialPattern(),
            new FanOutPattern(),
            new HierarchicalPattern(),
            new ReflexivePattern(),
            new PianoPattern(),
        ];
    }

    /**
     * Return config without exposing callable internals in traces.
     */
    protected function safeConfig(): array
    {
        $config = $this->config->toArray();
        foreach (['workers', 'supervisor', 'producer', 'verifier', 'fallback', 'patterns'] as $key) {
            if (!Arr::hasKey($config, $key)) {
                continue;
            }

            $config[$key] = Arr::is($config[$key]) ? Arr::keys($config[$key]) : 'configured';
        }

        return $config;
    }
}
