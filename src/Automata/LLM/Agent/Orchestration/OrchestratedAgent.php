<?php

namespace BlueFission\Automata\LLM\Agent\Orchestration;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;

class OrchestratedAgent
{
    protected string $name;
    protected Orchestrator $orchestrator;
    protected array $scope;

    /**
     * Wrap an orchestration so it can be used as one worker in a parent orchestration.
     */
    public function __construct(string $name, Orchestrator|OrchestrationConfig|array $orchestration, array $scope = [])
    {
        $this->name = $name;
        $this->orchestrator = $orchestration instanceof Orchestrator
            ? $orchestration
            : new Orchestrator($orchestration);
        $this->scope = ToolDefinition::mergeConfig([
            'include' => [],
            'shared' => [],
            'include_prior_results' => false,
            'prior_results_key' => 'prior_results',
        ], $scope);
    }

    /**
     * Return the worker-facing agent name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Execute the inner orchestration as a black-box worker.
     */
    public function __invoke(array $context = [], array $priorResults = []): array
    {
        $result = $this->orchestrator->run($this->scopedContext($context, $priorResults));
        $payload = $result->toArray();

        return [
            'output' => $payload['output'] ?? null,
            'confidence' => $payload['confidence'] ?? 1.0,
            'metadata' => [
                'black_box' => true,
                'orchestrated_agent' => $this->name,
                'orchestration' => $payload,
            ],
        ];
    }

    /**
     * Return an explicit worker definition for configs that prefer metadata.
     */
    public function worker(): array
    {
        return [
            'handler' => $this,
            'metadata' => [
                'black_box' => true,
                'orchestrated_agent' => $this->name,
            ],
        ];
    }

    /**
     * Build the limited inner context from parent context and shared scope.
     */
    protected function scopedContext(array $context, array $priorResults): array
    {
        $included = Arr::make($this->scope['include'] ?? [])->toArray();
        $scoped = Arr::count($included) === 0
            ? $context
            : $this->onlyContext($context, $included);

        foreach (Arr::make($this->scope['shared'] ?? [])->toArray() as $key => $value) {
            $scoped[$key] = $value;
        }

        if ($this->scope['include_prior_results'] ?? false) {
            $scoped[(string)($this->scope['prior_results_key'] ?? 'prior_results')] = $priorResults;
        }

        return $scoped;
    }

    /**
     * Copy only allowed top-level context keys into the child orchestration.
     */
    protected function onlyContext(array $context, array $keys): array
    {
        $scoped = [];
        $input = Arr::is($context['input'] ?? null) ? $context['input'] : [];
        foreach ($keys as $key) {
            $key = (string)$key;
            if (Arr::hasKey($context, $key)) {
                $scoped[$key] = $context[$key];
                continue;
            }

            if (Arr::hasKey($input, $key)) {
                $scoped[$key] = $input[$key];
            }
        }

        return $scoped;
    }
}
