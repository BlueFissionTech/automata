<?php

namespace BlueFission\Automata\LLM\Agent;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Tools\ITool;
use BlueFission\Behavioral\Configurable;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Behavioral\IConfigurable;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;
use BlueFission\Net\HTTP;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;

class ToolDefinition implements IConfigurable, IDispatcher
{
    use Configurable {
        Configurable::__construct as private __configurableConstruct;
    }

    public const PERMISSION_READ = 'read';
    public const PERMISSION_WRITE = 'write';
    public const PERMISSION_CRITICAL = 'critical';

    protected array $_config = [];

    /**
     * Create a deterministic contract for an agent tool.
     */
    public function __construct(array $config = [])
    {
        $this->_config = $this->defaults();
        $this->__configurableConstruct(self::mergeConfig($this->_config, $config));
        $this->normalizeConfig();
    }

    /**
     * Create a definition from an existing Automata tool implementation.
     */
    public static function fromTool(string $name, ITool $tool, array $config = []): self
    {
        return new self(self::mergeConfig([
            'name' => $name,
            'description' => $tool->description(),
            'purpose' => $tool->description(),
        ], $config));
    }

    /**
     * Create a definition from a plain configuration array.
     */
    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    /**
     * Merge tool configuration recursively while DevElation receives a native helper.
     */
    public static function mergeConfig(array ...$configs): array
    {
        $merged = [];
        foreach ($configs as $config) {
            foreach ($config as $key => $value) {
                if (Arr::hasKey($merged, $key) && Arr::is($merged[$key]) && Arr::is($value)) {
                    $merged[$key] = self::mergeConfig($merged[$key], $value);
                    continue;
                }

                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Return the tool name exposed to the model.
     */
    public function name(): string
    {
        return (string)$this->_config['name'];
    }

    /**
     * Return the human-facing description of the tool.
     */
    public function description(): string
    {
        return (string)$this->_config['description'];
    }

    /**
     * Return the selection purpose used in the prompt contract.
     */
    public function purpose(): string
    {
        return (string)$this->_config['purpose'];
    }

    /**
     * Return the broad tool category.
     */
    public function category(): string
    {
        return (string)$this->_config['category'];
    }

    /**
     * Return the permission class required by this tool.
     */
    public function permission(): string
    {
        return (string)$this->_config['permission'];
    }

    /**
     * Determine whether execution must be explicitly approved.
     */
    public function requiresApproval(): bool
    {
        return (bool)$this->_config['requires_approval'] || $this->permission() === self::PERMISSION_CRITICAL;
    }

    /**
     * Return the expected input schema.
     */
    public function inputSchema(): array
    {
        return Arr::make($this->_config['input_schema'] ?? [])->toArray();
    }

    /**
     * Return the expected output schema.
     */
    public function outputSchema(): array
    {
        return Arr::make($this->_config['output_schema'] ?? [])->toArray();
    }

    /**
     * Return retrieval tags used for catalog filtering.
     */
    public function tags(): array
    {
        return Arr::make($this->_config['tags'] ?? [])->toArray();
    }

    /**
     * Return named groups that can be retrieved together.
     */
    public function groups(): array
    {
        return Arr::make($this->_config['groups'] ?? [])->toArray();
    }

    /**
     * Return axis-to-term taxonomy entries for scoped retrieval.
     */
    public function taxonomy(): array
    {
        return Arr::make($this->_config['taxonomy'] ?? [])->toArray();
    }

    /**
     * Return other tool names that must complete before this one can run.
     */
    public function dependencies(): array
    {
        return Arr::make($this->_config['dependencies'] ?? [])->toArray();
    }

    /**
     * Return the execution timeout budget in seconds.
     */
    public function timeoutSeconds(): int
    {
        return max(0, (int)$this->_config['timeout_seconds']);
    }

    /**
     * Return retry count after the first execution attempt.
     */
    public function maxRetries(): int
    {
        return max(0, (int)$this->_config['max_retries']);
    }

    /**
     * Return the number of failures allowed before the circuit opens.
     */
    public function failureThreshold(): int
    {
        return max(1, (int)$this->_config['failure_threshold']);
    }

    /**
     * Determine whether this tool may safely run beside independent calls.
     */
    public function parallelSafe(): bool
    {
        return (bool)$this->_config['parallel_safe'];
    }

    /**
     * Return positive guidance for when the model should select the tool.
     */
    public function decisionBoundary(): string
    {
        return (string)$this->_config['decision_boundary'];
    }

    /**
     * Return negative guidance for when the model should skip the tool.
     */
    public function negativeGuidance(): string
    {
        return (string)$this->_config['negative_guidance'];
    }

    /**
     * Validate and normalize proposed tool input against the contract.
     */
    public function validateInput(mixed $input): ToolExecutionResult
    {
        $schema = $this->inputSchema();
        if (!$schema) {
            return ToolExecutionResult::success([
                'input' => $input,
                'normalized' => false,
            ]);
        }

        $normalized = $this->normalizeInput($input, $schema);
        $errors = $this->validateValue($normalized, $schema, 'input');

        if ($errors) {
            return ToolExecutionResult::validationError('Tool input did not match the contract.', [
                'tool' => $this->name(),
                'errors' => $errors,
            ]);
        }

        return ToolExecutionResult::success([
            'input' => $normalized,
            'normalized' => true,
        ]);
    }

    /**
     * Check permission context before deterministic execution.
     */
    public function allows(array $context = []): ToolExecutionResult
    {
        if (!$this->requiresApproval()) {
            return ToolExecutionResult::success(['allowed' => true]);
        }

        if (($context['approved'] ?? false) === true) {
            return ToolExecutionResult::success(['allowed' => true]);
        }

        Dev::do(AgentHook::PERMISSION_REQUEST, [
            'tool' => $this->name(),
            'permission' => $this->permission(),
            'context' => $context,
        ]);

        return ToolExecutionResult::error(
            'approval_required',
            'Tool requires explicit approval before execution.',
            ['tool' => $this->name(), 'permission' => $this->permission()],
            [],
            ToolExecutionResult::STATUS_PERMISSION_DENIED
        );
    }

    /**
     * Determine whether the definition belongs in a filtered catalog view.
     */
    public function matches(array $filters = []): bool
    {
        if (Arr::hasKey($filters, ToolCatalog::FILTER_CATEGORY) && $filters[ToolCatalog::FILTER_CATEGORY] !== $this->category()) {
            return false;
        }

        if (Arr::hasKey($filters, ToolCatalog::FILTER_PERMISSION)) {
            $permissions = Arr::make($filters[ToolCatalog::FILTER_PERMISSION])->toArray();
            if (!Arr::contains($permissions, $this->permission(), true)) {
                return false;
            }
        }

        if (Arr::hasKey($filters, ToolCatalog::FILTER_TAGS) && !$this->hasAnyTag($filters[ToolCatalog::FILTER_TAGS])) {
            return false;
        }

        if (Arr::hasKey($filters, ToolCatalog::FILTER_TAGS_ALL) && !$this->hasAllTags($filters[ToolCatalog::FILTER_TAGS_ALL])) {
            return false;
        }

        if (Arr::hasKey($filters, ToolCatalog::FILTER_GROUPS) && !$this->hasAnyGroup($filters[ToolCatalog::FILTER_GROUPS])) {
            return false;
        }

        if (Arr::hasKey($filters, ToolCatalog::FILTER_TAXONOMY) && !$this->matchesTaxonomy($filters[ToolCatalog::FILTER_TAXONOMY])) {
            return false;
        }

        if (Arr::hasKey($filters, ToolCatalog::FILTER_PARALLEL_SAFE) && (bool)$filters[ToolCatalog::FILTER_PARALLEL_SAFE] !== $this->parallelSafe()) {
            return false;
        }

        return true;
    }

    /**
     * Check if any requested tag is present.
     */
    public function hasAnyTag(mixed $tags): bool
    {
        return (bool)Arr::intersect(Arr::make($tags)->toArray(), $this->tags());
    }

    /**
     * Check if every requested tag is present.
     */
    public function hasAllTags(mixed $tags): bool
    {
        foreach (Arr::make($tags)->toArray() as $tag) {
            if (!Arr::contains($this->tags(), $tag, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if any requested group is present.
     */
    public function hasAnyGroup(mixed $groups): bool
    {
        return (bool)Arr::intersect(Arr::make($groups)->toArray(), $this->groups());
    }

    /**
     * Check if the taxonomy contains the requested axis and terms.
     */
    public function hasTaxonomy(string $axis, mixed $terms = null): bool
    {
        $taxonomy = $this->taxonomy();
        if (!Arr::hasKey($taxonomy, $axis)) {
            return false;
        }

        if (Val::isNull($terms)) {
            return true;
        }

        return (bool)Arr::intersect(Arr::make($terms)->toArray(), Arr::make($taxonomy[$axis])->toArray());
    }

    /**
     * Render the contract into the compact prompt form consumed by Agent.
     */
    public function formatForPrompt(): string
    {
        $parts = [
            $this->name() . ': ' . $this->purpose(),
        ];

        if ($this->decisionBoundary() !== '') {
            $parts[] = 'Use when: ' . $this->decisionBoundary();
        }

        if ($this->negativeGuidance() !== '') {
            $parts[] = 'Do not use when: ' . $this->negativeGuidance();
        }

        if ($this->tags()) {
            $parts[] = 'Tags: ' . self::joinStrings($this->tags(), ', ');
        }

        if ($this->groups()) {
            $parts[] = 'Groups: ' . self::joinStrings($this->groups(), ', ');
        }

        if ($this->taxonomy()) {
            $parts[] = 'Taxonomy: ' . HTTP::jsonEncode($this->taxonomy());
        }

        $parts[] = 'Permission: ' . $this->permission() . ($this->requiresApproval() ? ' (approval required)' : '');
        $parts[] = 'Parallel safe: ' . ($this->parallelSafe() ? 'yes' : 'no');

        if ($this->inputSchema()) {
            $parts[] = 'Input schema: ' . HTTP::jsonEncode($this->inputSchema());
        }

        if ($this->outputSchema()) {
            $parts[] = 'Output schema: ' . HTTP::jsonEncode($this->outputSchema());
        }

        return self::joinStrings($parts, "\n  ");
    }

    /**
     * Return the normalized configuration as an array.
     */
    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.tool_definition.to_array', $this->_config);
    }

    /**
     * Return the default contract fields accepted by Configurable.
     */
    protected function defaults(): array
    {
        return [
            'name' => '',
            'description' => '',
            'purpose' => '',
            'category' => 'general',
            'tags' => [],
            'groups' => [],
            'taxonomy' => [],
            'input_schema' => [],
            'output_schema' => [],
            'decision_boundary' => '',
            'negative_guidance' => '',
            'permission' => self::PERMISSION_READ,
            'requires_approval' => false,
            'parallel_safe' => false,
            'dependencies' => [],
            'timeout_seconds' => 0,
            'max_retries' => 0,
            'retry_backoff_ms' => 0,
            'failure_threshold' => 3,
        ];
    }

    /**
     * Normalize free-form config values after Configurable assignment.
     */
    protected function normalizeConfig(): void
    {
        $this->_config['name'] = Str::trim((string)$this->_config['name']);
        $this->_config['description'] = Str::trim((string)$this->_config['description']);
        $this->_config['purpose'] = Str::trim((string)($this->_config['purpose'] ?: $this->_config['description']));
        $this->_config['tags'] = self::normalizeStringList($this->_config['tags'] ?? []);
        $this->_config['groups'] = self::normalizeStringList($this->_config['groups'] ?? []);
        $this->_config['dependencies'] = self::normalizeStringList($this->_config['dependencies'] ?? []);
        $this->_config['taxonomy'] = self::normalizeTaxonomy($this->_config['taxonomy'] ?? []);
    }

    /**
     * Coerce a list-like value into unique string entries.
     */
    protected static function normalizeStringList(mixed $value): array
    {
        $strings = (new Collection(Arr::make($value)->toArray()))
            ->map(fn ($item): string => Str::trim((string)$item))
            ->filter(fn (string $item): bool => $item !== '')
            ->toArray();

        $unique = [];
        foreach (Arr::unique($strings) as $item) {
            $unique[] = $item;
        }

        return $unique;
    }

    /**
     * Normalize axis-based taxonomy values into string lists.
     */
    protected static function normalizeTaxonomy(mixed $value): array
    {
        if (!Arr::is($value)) {
            return [];
        }

        $taxonomy = [];
        foreach ($value as $axis => $terms) {
            $axis = Str::trim((string)$axis);
            if ($axis === '') {
                continue;
            }

            $taxonomy[$axis] = self::normalizeStringList($terms);
        }

        return $taxonomy;
    }

    /**
     * Decode simple object payloads and fill the only obvious schema field.
     */
    protected function normalizeInput(mixed $input, array $schema): mixed
    {
        $type = $schema['type'] ?? null;

        if ($type === 'object' && Str::is($input)) {
            $decoded = json_decode((string)$input, true);
            if (json_last_error() === JSON_ERROR_NONE && Arr::is($decoded)) {
                return $decoded;
            }

            $properties = Arr::make($schema['properties'] ?? [])->toArray();
            $required = Arr::make($schema['required'] ?? [])->toArray();
            if (Arr::count($properties) === 1) {
                $field = Arr::keys($properties)[0] ?? null;
                return [$field => (string)$input];
            }

            if (Arr::count($required) === 1) {
                return [$required[0] => (string)$input];
            }
        }

        return $input;
    }

    /**
     * Validate a normalized value against a schema node.
     */
    protected function validateValue(mixed $value, array $schema, string $path): array
    {
        $errors = [];
        $type = $schema['type'] ?? null;

        if ($type && !$this->matchesType($value, (string)$type)) {
            $errors[] = "{$path} must be {$type}.";
            return $errors;
        }

        if (Arr::hasKey($schema, 'enum') && Arr::is($schema['enum']) && !Arr::contains($schema['enum'], $value, true)) {
            $errors[] = "{$path} must be one of: " . self::joinStrings(self::normalizeStringList($schema['enum']), ', ');
        }

        if (($type ?? null) === 'object') {
            $properties = Arr::make($schema['properties'] ?? [])->toArray();
            $required = Arr::make($schema['required'] ?? [])->toArray();
            foreach ($required as $field) {
                if (!Arr::is($value) || !Arr::hasKey($value, $field) || $value[$field] === null || $value[$field] === '') {
                    $errors[] = "{$path}.{$field} is required.";
                }
            }

            if (Arr::is($value)) {
                foreach ($properties as $field => $fieldSchema) {
                    if (!Arr::hasKey($value, $field)) {
                        continue;
                    }

                    foreach ($this->validateValue($value[$field], $fieldSchema, "{$path}.{$field}") as $error) {
                        $errors[] = $error;
                    }
                }
            }
        }

        if (($type ?? null) === 'array' && Arr::hasKey($schema, 'items') && Arr::is($value)) {
            foreach ($value as $index => $item) {
                foreach ($this->validateValue($item, $schema['items'], "{$path}.{$index}") as $error) {
                    $errors[] = $error;
                }
            }
        }

        if (Arr::hasKey($schema, 'minimum') && Num::is($value) && $value < $schema['minimum']) {
            $errors[] = "{$path} must be at least {$schema['minimum']}.";
        }

        if (Arr::hasKey($schema, 'maximum') && Num::is($value) && $value > $schema['maximum']) {
            $errors[] = "{$path} must be at most {$schema['maximum']}.";
        }

        return $errors;
    }

    /**
     * Match JSON-schema-style primitive names to DevElation value helpers.
     */
    protected function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => Str::is($value),
            'integer', 'int' => gettype($value) === 'integer',
            'number', 'float' => gettype($value) === 'integer' || gettype($value) === 'double',
            'boolean', 'bool' => gettype($value) === 'boolean',
            'array' => Arr::is($value),
            'object' => Arr::is($value),
            'null' => $value === null,
            default => Val::is($value),
        };
    }

    /**
     * Match axis-to-term taxonomy filters.
     */
    protected function matchesTaxonomy(mixed $filter): bool
    {
        if (!Arr::is($filter)) {
            return false;
        }

        foreach ($filter as $axis => $terms) {
            if (!$this->hasTaxonomy((string)$axis, $terms)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Join strings without scattering delimiter logic across prompt rendering.
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
