<?php

namespace BlueFission\Automata\Media\Processing;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\DevElation as Dev;

class HandlerRegistry
{
    protected array $_handlers = [];

    public function register(string $capability, $handler, ?string $name = null, ?float $weight = null): void
    {
        $capability = strtolower(trim($capability));
        if ($capability === '') {
            return;
        }

        if (!isset($this->_handlers[$capability])) {
            $this->_handlers[$capability] = new OrganizedCollection();
        }

        $collection = $this->_handlers[$capability];
        $name = $name ?? $this->inferName($handler, $capability);

        $collection->add($handler, $name);

        if ($weight !== null) {
            $collection->weight($name, (float)$weight);
            $collection->sort();
        }

        Dev::do('media.processing.registry.registered', [
            'capability' => $capability,
            'name' => $name,
            'weight' => $weight,
        ]);
    }

    public function resolve(string $capability)
    {
        $capability = strtolower(trim($capability));
        $collection = $this->_handlers[$capability] ?? null;
        if (!$collection instanceof OrganizedCollection) {
            return null;
        }

        foreach ($collection->contents() as $entry) {
            $handler = $entry['value'] ?? null;
            if ($this->isAvailable($handler)) {
                return $handler;
            }
        }

        return null;
    }

    public function resolveAll(string $capability): array
    {
        $capability = strtolower(trim($capability));
        $collection = $this->_handlers[$capability] ?? null;
        if (!$collection instanceof OrganizedCollection) {
            return [];
        }

        $handlers = [];
        foreach ($collection->contents() as $entry) {
            $handler = $entry['value'] ?? null;
            if ($this->isAvailable($handler)) {
                $handlers[] = $handler;
            }
        }

        return $handlers;
    }

    protected function isAvailable($handler): bool
    {
        if (is_object($handler) && method_exists($handler, 'isAvailable')) {
            return (bool)$handler->isAvailable();
        }

        return is_callable($handler);
    }

    protected function inferName($handler, string $capability): string
    {
        if (is_object($handler)) {
            return get_class($handler);
        }

        if (is_string($handler)) {
            return $handler;
        }

        return $capability . '_' . uniqid();
    }
}
