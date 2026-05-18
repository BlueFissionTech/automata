<?php

namespace BlueFission\Automata\LLM\Agent;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Tools\ITool;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;
use BlueFission\Num;

class ToolCatalog
{
    public const FILTER_CATEGORY = 'category';
    public const FILTER_PERMISSION = 'permission';
    public const FILTER_TAGS = 'tags';
    public const FILTER_TAGS_ALL = 'tags_all';
    public const FILTER_GROUPS = 'groups';
    public const FILTER_TAXONOMY = 'taxonomy';
    public const FILTER_INCLUDE = 'include';
    public const FILTER_EXCLUDE = 'exclude';
    public const FILTER_LIMIT = 'limit';
    public const FILTER_PARALLEL_SAFE = 'parallel_safe';

    protected array $tools = [];

    protected array $definitions = [];

    /**
     * Register an executable tool and its deterministic model-facing contract.
     */
    public function register(string $name, ITool $tool, ToolDefinition|array|null $definition = null): self
    {
        $this->tools[$name] = $tool;

        if ($definition instanceof ToolDefinition) {
            $this->definitions[$name] = $definition;
        } elseif (Arr::is($definition)) {
            $this->definitions[$name] = new ToolDefinition(ToolDefinition::mergeConfig([
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

    /**
     * Register or replace only the contract for a named tool.
     */
    public function define(string $name, ToolDefinition|array $definition): self
    {
        $this->definitions[$name] = $definition instanceof ToolDefinition
            ? $definition
            : new ToolDefinition(ToolDefinition::mergeConfig(['name' => $name], $definition));

        return $this;
    }

    /**
     * Retrieve an executable tool by name.
     */
    public function tool(string $name): ?ITool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Retrieve the contract for a named tool.
     */
    public function definition(string $name): ?ToolDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    /**
     * Return registered names after applying catalog filters.
     */
    public function names(array $filters = []): array
    {
        return Arr::keys($this->definitions($filters));
    }

    /**
     * Return executable tools without filtering definitions.
     */
    public function tools(): array
    {
        return $this->tools;
    }

    /**
     * Return definitions matching filters, include/exclude lists, and limits.
     */
    public function definitions(array $filters = []): array
    {
        $definitions = (new Collection($this->definitions))
            ->filter(fn (ToolDefinition $definition): bool => $definition->matches($filters))
            ->toArray();

        if (Arr::hasKey($filters, self::FILTER_INCLUDE)) {
            $include = Arr::make($filters[self::FILTER_INCLUDE])->toArray();
            $definitions = (new Collection($definitions))
                ->filter(fn ($definition, $name): bool => Arr::contains($include, $name, true))
                ->toArray();
        }

        if (Arr::hasKey($filters, self::FILTER_EXCLUDE)) {
            $exclude = Arr::make($filters[self::FILTER_EXCLUDE])->toArray();
            $definitions = (new Collection($definitions))
                ->filter(fn ($definition, $name): bool => !Arr::contains($exclude, $name, true))
                ->toArray();
        }

        if (Arr::hasKey($filters, self::FILTER_LIMIT) && Num::is($filters[self::FILTER_LIMIT])) {
            $limited = [];
            $limit = max(0, (int)$filters[self::FILTER_LIMIT]);
            foreach ($definitions as $name => $definition) {
                if (Arr::make($limited)->count() >= $limit) {
                    break;
                }

                $limited[$name] = $definition;
            }

            $definitions = $limited;
        }

        return $definitions;
    }

    /**
     * Return tool definitions that have any of the requested tags.
     */
    public function tagged(mixed $tags, bool $requireAll = false): array
    {
        return $this->definitions([
            $requireAll ? self::FILTER_TAGS_ALL : self::FILTER_TAGS => $tags,
        ]);
    }

    /**
     * Return tool definitions assigned to the requested groups.
     */
    public function grouped(mixed $groups): array
    {
        return $this->definitions([
            self::FILTER_GROUPS => $groups,
        ]);
    }

    /**
     * Return tool definitions that match taxonomy axis terms.
     */
    public function taxonomized(string $axis, mixed $terms = null): array
    {
        return $this->definitions([
            self::FILTER_TAXONOMY => [
                $axis => $terms,
            ],
        ]);
    }

    /**
     * Render the filtered catalog into a prompt-ready list.
     */
    public function promptList(array $filters = []): string
    {
        $lines = [];
        foreach ($this->definitions($filters) as $definition) {
            $lines[] = $definition->formatForPrompt();
        }

        return self::joinStrings($lines, "\n");
    }

    /**
     * Return filtered definitions as normalized arrays.
     */
    public function toArray(array $filters = []): array
    {
        $rows = [];
        foreach ($this->definitions($filters) as $name => $definition) {
            $rows[$name] = $definition->toArray();
        }

        return $rows;
    }

    /**
     * Join strings without coupling callers to PHP array helpers.
     */
    protected static function joinStrings(array $values, string $glue): string
    {
        $output = '';
        foreach ($values as $value) {
            $output .= $output === '' ? (string)$value : $glue . (string)$value;
        }

        return $output;
    }
}
