<?php

namespace BlueFission\Automata\Adapters;

use BlueFission\Arr;
use BlueFission\Data\IData;
use BlueFission\IVal;
use BlueFission\Obj;
use InvalidArgumentException;

/**
 * Normalizes shared runtime state across arrays, Arr, Obj, and IData carriers.
 *
 * This adapter intentionally does not impose worldview semantics; it only
 * provides a common state read/write signature over the carrier layer.
 */
class StateAdapter
{
    private Arr $state;

    public function __construct(private mixed $source = [])
    {
        $this->state = new Arr($this->seedState($source));
    }

    public static function wrap(mixed $source = []): self
    {
        return new self($source);
    }

    public function source(): mixed
    {
        return $this->source;
    }

    public function state(): Arr
    {
        return $this->state;
    }

    public function get(string|array|null $path = null, mixed $default = null): mixed
    {
        if ($path === null) {
            return $this->snapshot();
        }

        return Arr::getPath($this->snapshot(), $path, $default);
    }

    public function set(string|array $path, mixed $value): self
    {
        $state = $this->snapshot();
        $segments = is_array($path) ? $path : explode('.', $path);
        $lastIndex = count($segments) - 1;

        if (empty($segments)) {
            return $this;
        }

        $cursor = &$state;

        foreach ($segments as $index => $segment) {
            if ($segment === '' || $segment === null) {
                continue;
            }

            if (!is_array($cursor)) {
                $cursor = [];
            }

            if ($index === $lastIndex) {
                $cursor[$segment] = $this->normalizeValue($value);
                break;
            }

            if (!array_key_exists($segment, $cursor) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $this->state->val($state);

        return $this;
    }

    public function merge(array $state): self
    {
        $merged = array_replace_recursive($this->snapshot(), $this->normalizeValue($state));
        $this->state->val($merged);

        return $this;
    }

    public function snapshot(): array
    {
        return $this->state->toArray();
    }

    public function sync(): mixed
    {
        $snapshot = $this->snapshot();

        if ($this->source instanceof Arr) {
            $this->source->val($snapshot);
            return $this->source;
        }

        if ($this->source instanceof IData) {
            if (method_exists($this->source, 'contents')) {
                $this->source->contents($snapshot);
            }

            if (method_exists($this->source, 'assign')) {
                $this->source->assign($snapshot);
            }

            return $this->source;
        }

        if ($this->source instanceof Obj) {
            $this->source->assign($snapshot);
            return $this->source;
        }

        $this->source = $snapshot;

        return $snapshot;
    }

    private function seedState(mixed $source): array
    {
        if ($source instanceof Arr) {
            return $source->toArray();
        }

        if ($source instanceof IData) {
            $contents = $this->normalizeValue($source->contents());
            if (!empty($contents)) {
                return is_array($contents) ? $contents : [];
            }

            $data = $this->normalizeValue($source->data());
            return is_array($data) ? $data : [];
        }

        if ($source instanceof Obj) {
            return $source->toArray();
        }

        if (is_array($source)) {
            return $this->normalizeValue($source);
        }

        if ($source === null) {
            return [];
        }

        throw new InvalidArgumentException('StateAdapter expects an array, Arr, Obj, or IData carrier.');
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Arr) {
            return $value->toArray();
        }

        if ($value instanceof IVal) {
            return $value->val();
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        if (is_object($value)) {
            if (method_exists($value, 'snapshot')) {
                return $value->snapshot();
            }

            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }
        }

        return $value;
    }
}
