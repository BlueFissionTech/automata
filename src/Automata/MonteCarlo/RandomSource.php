<?php

namespace BlueFission\Automata\MonteCarlo;

class RandomSource
{
    private int $state;

    public function __construct(?int $seed = null)
    {
        $seed = $seed ?? (int)(microtime(true) * 1000000);
        $this->state = $seed & 0x7fffffff;
    }

    public function nextFloat(): float
    {
        $this->state = (int)(($this->state * 1103515245 + 12345) & 0x7fffffff);

        return $this->state / 2147483647;
    }

    public function nextInt(int $min, int $max): int
    {
        if ($max < $min) {
            throw new \InvalidArgumentException('Maximum must be greater than or equal to minimum.');
        }

        if ($min === $max) {
            return $min;
        }

        return $min + (int)floor($this->nextFloat() * (($max - $min) + 1));
    }

    public function pick(array $items)
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('Cannot pick from an empty array.');
        }

        return $items[$this->nextInt(0, count($items) - 1)];
    }
}
