<?php

namespace Examples\DisasterResponse\Sim;

class Position
{
    private int $x;
    private int $y;

    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function x(): int
    {
        return $this->x;
    }

    public function y(): int
    {
        return $this->y;
    }

    public function equals(Position $other): bool
    {
        return $this->x === $other->x() && $this->y === $other->y();
    }

    public function toKey(): string
    {
        return $this->x . ',' . $this->y;
    }

    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }
}
