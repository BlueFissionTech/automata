<?php

namespace BlueFission\Tests\Automata\Support;

use BlueFission\Arr;
use BlueFission\Automata\Support\StructureFactory;
use BlueFission\Vec;

class RecordingStructureFactory extends StructureFactory
{
    public int $arrCalls = 0;
    public int $vecCalls = 0;
    public int $valuesCalls = 0;
    public int $fillCalls = 0;

    public function arr(mixed $value = []): Arr
    {
        $this->arrCalls++;

        return parent::arr($value);
    }

    public function vec(mixed $value = []): Vec
    {
        $this->vecCalls++;

        return parent::vec($value);
    }

    public function values(mixed $value): array
    {
        $this->valuesCalls++;

        return parent::values($value);
    }

    public function fill(int $count, mixed $value): array
    {
        $this->fillCalls++;

        return parent::fill($count, $value);
    }
}
