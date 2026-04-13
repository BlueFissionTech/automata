<?php

namespace BlueFission\Automata\Adapters;

use BlueFission\Obj;

/**
 * Thin adapter over a DevElation Obj carrier.
 *
 * This preserves the underlying carrier mechanics and only normalizes field
 * access plus snapshot export for downstream utilities.
 */
class CarrierAdapter
{
    public function __construct(private Obj $carrier)
    {
    }

    public function carrier(): Obj
    {
        return $this->carrier;
    }

    public function field(string $field, mixed $value = null): mixed
    {
        if (func_num_args() === 1) {
            return $this->carrier->field($field);
        }

        $this->carrier->field($field, $value);

        return $this;
    }

    public function assign(array|object $data): self
    {
        $this->carrier->assign($data);

        return $this;
    }

    public function snapshot(): array
    {
        return $this->carrier->toArray();
    }
}
