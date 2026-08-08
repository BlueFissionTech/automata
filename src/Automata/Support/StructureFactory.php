<?php

namespace BlueFission\Automata\Support;

use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Dict;
use BlueFission\Set;
use BlueFission\Vec;

class StructureFactory implements IStructureFactory
{
    public function arr(mixed $value = []): Arr
    {
        return Arr::make($value);
    }

    public function vec(mixed $value = []): Vec
    {
        return new Vec($value);
    }

    public function dict(mixed $value = []): Dict
    {
        return new Dict($value);
    }

    public function set(mixed $value = []): Set
    {
        return new Set($value);
    }

    public function collection(mixed $value = []): Collection
    {
        return new Collection($this->values($value));
    }

    public function values(mixed $value): array
    {
        return $this->arr($value)->values()->val();
    }

    public function fill(int $count, mixed $value): array
    {
        return $count > 0 ? array_fill(0, $count, $value) : [];
    }
}
