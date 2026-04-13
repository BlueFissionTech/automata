<?php

namespace BlueFission\Automata\Adapters;

use BlueFission\Arr;
use BlueFission\Data\IData;
use BlueFission\IVal;

/**
 * Thin adapter over an IData store carrier.
 *
 * This keeps persistence mechanics in the store itself while exposing a stable
 * snapshot-oriented surface to runtime utilities.
 */
class StoreAdapter
{
    public function __construct(private IData $store)
    {
    }

    public function store(): IData
    {
        return $this->store;
    }

    public function read(): self
    {
        $this->store->read();

        return $this;
    }

    public function write(?array $data = null): self
    {
        if ($data !== null) {
            $this->contents($data);

            if (method_exists($this->store, 'assign')) {
                $this->store->assign($data);
            }
        }

        $this->store->write();

        return $this;
    }

    public function contents(mixed $contents = null): mixed
    {
        if (func_num_args() === 0) {
            return $this->store->contents();
        }

        $this->store->contents($contents);

        return $this;
    }

    public function data(): mixed
    {
        return $this->store->data();
    }

    public function status(?string $message = null): mixed
    {
        return $this->store->status($message);
    }

    public function snapshot(): array
    {
        $contents = $this->normalize($this->store->contents());
        if (!empty($contents) && is_array($contents)) {
            return $contents;
        }

        $data = $this->normalize($this->store->data());

        return is_array($data) ? $data : [];
    }

    private function normalize(mixed $value): mixed
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
                $normalized[$key] = $this->normalize($item);
            }

            return $normalized;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }
}
