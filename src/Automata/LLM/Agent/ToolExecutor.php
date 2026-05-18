<?php

namespace BlueFission\Automata\LLM\Agent;

use BlueFission\DevElation as Dev;
use Throwable;

class ToolExecutor
{
    protected array $failures = [];

    /**
     * Validate, authorize, execute, and normalize a tool call.
     */
    public function execute(ToolCatalog $catalog, string $name, mixed $input = null, array $context = []): ToolExecutionResult
    {
        $tool = $catalog->tool($name);
        $definition = $catalog->definition($name);

        if (!$tool || !$definition) {
            return ToolExecutionResult::error(
                'unknown_tool',
                "Tool '{$name}' is not registered.",
                ['tool' => $name],
                [],
                ToolExecutionResult::STATUS_UNAVAILABLE
            );
        }

        if (($this->failures[$name] ?? 0) >= $definition->failureThreshold()) {
            return ToolExecutionResult::error(
                'circuit_open',
                'Tool circuit breaker is open after repeated failures.',
                ['tool' => $name, 'failures' => $this->failures[$name]],
                [],
                ToolExecutionResult::STATUS_CIRCUIT_OPEN
            );
        }

        $permission = $definition->allows($context);
        if (!$permission->ok()) {
            return $permission;
        }

        $validation = $definition->validateInput($input);
        if (!$validation->ok()) {
            $this->failures[$name] = ($this->failures[$name] ?? 0) + 1;
            return $validation;
        }

        $payload = $validation->payload();
        $preparedInput = $payload['input'] ?? $input;
        $attempts = $definition->maxRetries() + 1;
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                Dev::do(AgentHook::PRE_TOOL_USE, [
                    'tool' => $name,
                    'attempt' => $attempt,
                    'input' => $preparedInput,
                    'context' => $context,
                ]);

                $output = $tool->execute($preparedInput);
                $this->failures[$name] = 0;

                $result = ToolExecutionResult::fromToolOutput($output, [
                    'tool' => $name,
                    'attempt' => $attempt,
                    'max_attempts' => $attempts,
                    'definition' => $definition->toArray(),
                ]);

                Dev::do(AgentHook::POST_TOOL_USE, $result->toArray());

                return $result;
            } catch (Throwable $exception) {
                $lastError = $exception;
                Dev::do('automata.llm.agent.tool_executor.error', [
                    'tool' => $name,
                    'attempt' => $attempt,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->failures[$name] = ($this->failures[$name] ?? 0) + 1;

        return ToolExecutionResult::error('tool_failed', 'Tool execution failed.', [
            'tool' => $name,
            'exception' => $lastError ? $lastError->getMessage() : null,
            'attempts' => $attempts,
        ]);
    }

    /**
     * Reset circuit-breaker failure counts for all tools or one named tool.
     */
    public function reset(?string $name = null): void
    {
        if ($name === null) {
            $this->failures = [];
            return;
        }

        unset($this->failures[$name]);
    }

    /**
     * Return failure counts used by circuit-breaker checks.
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
