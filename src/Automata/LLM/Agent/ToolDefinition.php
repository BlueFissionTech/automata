<?php

namespace BlueFission\Automata\LLM\Agent;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Tools\ITool;
use BlueFission\DevElation as Dev;
use BlueFission\Str;
use BlueFission\Val;

class ToolDefinition
{
    public const PERMISSION_READ = 'read';
    public const PERMISSION_WRITE = 'write';
    public const PERMISSION_CRITICAL = 'critical';

    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive($this->defaults(), $config);
        $this->config['name'] = Str::trim((string)$this->config['name']);
        $this->config['description'] = Str::trim((string)$this->config['description']);
        $this->config['purpose'] = Str::trim((string)($this->config['purpose'] ?: $this->config['description']));
        $this->config['tags'] = array_values(array_unique(array_map('strval', $this->config['tags'] ?? [])));
        $this->config['dependencies'] = array_values(array_unique(array_map('strval', $this->config['dependencies'] ?? [])));
    }

    public static function fromTool(string $name, ITool $tool, array $config = []): self
    {
        return new self(array_replace_recursive([
            'name' => $name,
            'description' => $tool->description(),
            'purpose' => $tool->description(),
        ], $config));
    }

    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    public function name(): string
    {
        return (string)$this->config['name'];
    }

    public function description(): string
    {
        return (string)$this->config['description'];
    }

    public function purpose(): string
    {
        return (string)$this->config['purpose'];
    }

    public function category(): string
    {
        return (string)$this->config['category'];
    }

    public function permission(): string
    {
        return (string)$this->config['permission'];
    }

    public function requiresApproval(): bool
    {
        return (bool)$this->config['requires_approval'] || $this->permission() === self::PERMISSION_CRITICAL;
    }

    public function inputSchema(): array
    {
        return $this->config['input_schema'] ?? [];
    }

    public function outputSchema(): array
    {
        return $this->config['output_schema'] ?? [];
    }

    public function tags(): array
    {
        return $this->config['tags'] ?? [];
    }

    public function dependencies(): array
    {
        return $this->config['dependencies'] ?? [];
    }

    public function timeoutSeconds(): int
    {
        return max(0, (int)$this->config['timeout_seconds']);
    }

    public function maxRetries(): int
    {
        return max(0, (int)$this->config['max_retries']);
    }

    public function failureThreshold(): int
    {
        return max(1, (int)$this->config['failure_threshold']);
    }

    public function parallelSafe(): bool
    {
        return (bool)$this->config['parallel_safe'];
    }

    public function decisionBoundary(): string
    {
        return (string)$this->config['decision_boundary'];
    }

    public function negativeGuidance(): string
    {
        return (string)$this->config['negative_guidance'];
    }

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

    public function allows(array $context = []): ToolExecutionResult
    {
        if (!$this->requiresApproval()) {
            return ToolExecutionResult::success(['allowed' => true]);
        }

        if (($context['approved'] ?? false) === true) {
            return ToolExecutionResult::success(['allowed' => true]);
        }

        return ToolExecutionResult::error(
            'approval_required',
            'Tool requires explicit approval before execution.',
            ['tool' => $this->name(), 'permission' => $this->permission()],
            [],
            ToolExecutionResult::STATUS_PERMISSION_DENIED
        );
    }

    public function matches(array $filters = []): bool
    {
        if (isset($filters['category']) && $filters['category'] !== $this->category()) {
            return false;
        }

        if (isset($filters['permission'])) {
            $permissions = Arr::is($filters['permission']) ? $filters['permission'] : [$filters['permission']];
            if (!in_array($this->permission(), $permissions, true)) {
                return false;
            }
        }

        if (isset($filters['tags'])) {
            $tags = Arr::is($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            if (!array_intersect($tags, $this->tags())) {
                return false;
            }
        }

        if (isset($filters['parallel_safe']) && (bool)$filters['parallel_safe'] !== $this->parallelSafe()) {
            return false;
        }

        return true;
    }

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

        $parts[] = 'Permission: ' . $this->permission() . ($this->requiresApproval() ? ' (approval required)' : '');
        $parts[] = 'Parallel safe: ' . ($this->parallelSafe() ? 'yes' : 'no');

        if ($this->inputSchema()) {
            $parts[] = 'Input schema: ' . json_encode($this->inputSchema());
        }

        if ($this->outputSchema()) {
            $parts[] = 'Output schema: ' . json_encode($this->outputSchema());
        }

        return implode("\n  ", $parts);
    }

    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.tool_definition.to_array', $this->config);
    }

    protected function defaults(): array
    {
        return [
            'name' => '',
            'description' => '',
            'purpose' => '',
            'category' => 'general',
            'tags' => [],
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

    protected function normalizeInput(mixed $input, array $schema): mixed
    {
        $type = $schema['type'] ?? null;

        if ($type === 'object' && Str::is($input)) {
            $decoded = json_decode((string)$input, true);
            if (json_last_error() === JSON_ERROR_NONE && Arr::is($decoded)) {
                return $decoded;
            }

            $properties = $schema['properties'] ?? [];
            $required = $schema['required'] ?? [];
            if (count($properties) === 1) {
                $field = array_key_first($properties);
                return [$field => (string)$input];
            }

            if (count($required) === 1) {
                return [$required[0] => (string)$input];
            }
        }

        return $input;
    }

    protected function validateValue(mixed $value, array $schema, string $path): array
    {
        $errors = [];
        $type = $schema['type'] ?? null;

        if ($type && !$this->matchesType($value, (string)$type)) {
            $errors[] = "{$path} must be {$type}.";
            return $errors;
        }

        if (isset($schema['enum']) && Arr::is($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = "{$path} must be one of: " . implode(', ', array_map('strval', $schema['enum']));
        }

        if (($type ?? null) === 'object') {
            $properties = $schema['properties'] ?? [];
            $required = $schema['required'] ?? [];
            foreach ($required as $field) {
                if (!Arr::is($value) || !array_key_exists($field, $value) || $value[$field] === null || $value[$field] === '') {
                    $errors[] = "{$path}.{$field} is required.";
                }
            }

            if (Arr::is($value)) {
                foreach ($properties as $field => $fieldSchema) {
                    if (array_key_exists($field, $value)) {
                        $errors = array_merge($errors, $this->validateValue($value[$field], $fieldSchema, "{$path}.{$field}"));
                    }
                }
            }
        }

        if (($type ?? null) === 'array' && isset($schema['items']) && Arr::is($value)) {
            foreach ($value as $index => $item) {
                $errors = array_merge($errors, $this->validateValue($item, $schema['items'], "{$path}.{$index}"));
            }
        }

        if (isset($schema['minimum']) && is_numeric($value) && $value < $schema['minimum']) {
            $errors[] = "{$path} must be at least {$schema['minimum']}.";
        }

        if (isset($schema['maximum']) && is_numeric($value) && $value > $schema['maximum']) {
            $errors[] = "{$path} must be at most {$schema['maximum']}.";
        }

        return $errors;
    }

    protected function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => Str::is($value),
            'integer', 'int' => is_int($value),
            'number', 'float' => is_int($value) || is_float($value),
            'boolean', 'bool' => is_bool($value),
            'array' => Arr::is($value),
            'object' => Arr::is($value),
            'null' => $value === null,
            default => Val::is($value),
        };
    }
}
