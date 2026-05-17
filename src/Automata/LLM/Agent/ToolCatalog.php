<?php

namespace BlueFission\Automata\LLM\Agent;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Tools\ITool;
use BlueFission\DevElation as Dev;

class ToolCatalog
{
    protected array $tools = [];
    protected array $definitions = [];

    public function register(string $name, ITool $tool, ToolDefinition|array|null $definition = null): self
    {
        $this->tools[$name] = $tool;

        if ($definition instanceof ToolDefinition) {
            $this->definitions[$name] = $definition;
        } elseif (Arr::is($definition)) {
            $this->definitions[$name] = new ToolDefinition(array_replace_recursive([
                'name' => $name,
                'description' => $tool->description(),
                'purpose' => $tool->description(),
            ], $definition));
        } else {
            $this->definitions[$name] = ToolDefinition::fromTool($name, $tool);
        }

        Dev::do('automata.llm.agent.tool_catalog.registered', [
            'name' => $name,
            'definition' => $this->definitions[$name]->toArray(),
        ]);

        return $this;
    }

    public function define(string $name, ToolDefinition|array $definition): self
    {
        $this->definitions[$name] = $definition instanceof ToolDefinition
            ? $definition
            : new ToolDefinition(array_replace_recursive(['name' => $name], $definition));

        return $this;
    }

    public function tool(string $name): ?ITool
    {
        return $this->tools[$name] ?? null;
    }

    public function definition(string $name): ?ToolDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    public function names(array $filters = []): array
    {
        return array_keys($this->definitions($filters));
    }

    public function tools(): array
    {
        return $this->tools;
    }

    public function definitions(array $filters = []): array
    {
        $definitions = array_filter(
            $this->definitions,
            fn (ToolDefinition $definition): bool => $definition->matches($filters)
        );

        if (isset($filters['include'])) {
            $include = Arr::is($filters['include']) ? $filters['include'] : [$filters['include']];
            $definitions = array_filter($definitions, fn ($definition, $name): bool => in_array($name, $include, true), ARRAY_FILTER_USE_BOTH);
        }

        if (isset($filters['exclude'])) {
            $exclude = Arr::is($filters['exclude']) ? $filters['exclude'] : [$filters['exclude']];
            $definitions = array_filter($definitions, fn ($definition, $name): bool => !in_array($name, $exclude, true), ARRAY_FILTER_USE_BOTH);
        }

        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $definitions = array_slice($definitions, 0, max(0, (int)$filters['limit']), true);
        }

        return $definitions;
    }

    public function promptList(array $filters = []): string
    {
        $lines = [];
        foreach ($this->definitions($filters) as $definition) {
            $lines[] = $definition->formatForPrompt();
        }

        return implode("\n", $lines);
    }

    public function toArray(array $filters = []): array
    {
        $rows = [];
        foreach ($this->definitions($filters) as $name => $definition) {
            $rows[$name] = $definition->toArray();
        }

        return $rows;
    }
}
