<?php

namespace BlueFission\Automata\LLM\Agent\Capability;

use BlueFission\Arr;
use InvalidArgumentException;

class CapabilityRegistry
{
    protected array $definitions = [];

    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public function register(CapabilityDefinition|array $definition): self
    {
        $definition = $definition instanceof CapabilityDefinition
            ? $definition
            : new CapabilityDefinition($definition);

        if ($definition->id() === '' || $definition->version() === '' || $definition->owner() === '') {
            throw new InvalidArgumentException('Capability id, version, and owner are required.');
        }

        $this->definitions[$this->key($definition->id(), $definition->version())] = $definition;

        return $this;
    }

    public function definition(string $id, string $version): ?CapabilityDefinition
    {
        return $this->definitions[$this->key($id, $version)] ?? null;
    }

    public function has(string $id, string $version): bool
    {
        return $this->definition($id, $version) instanceof CapabilityDefinition;
    }

    public function definitions(?string $owner = null, ?string $availability = null): array
    {
        return Arr::make($this->definitions)
            ->filter(static fn (CapabilityDefinition $definition): bool =>
                ($owner === null || $definition->owner() === $owner)
                && ($availability === null || $definition->availability() === $availability))
            ->toArray();
    }

    public function toArray(): array
    {
        return Arr::make($this->definitions)
            ->map(static fn (CapabilityDefinition $definition): array => $definition->toArray())
            ->toArray();
    }

    protected function key(string $id, string $version): string
    {
        return $id . '@' . $version;
    }
}
