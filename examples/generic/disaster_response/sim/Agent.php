<?php

namespace Examples\DisasterResponse\Sim;

class Agent
{
    private string $id;
    private Position $position;

    public function __construct(string $id, Position $position)
    {
        $this->id = $id;
        $this->position = $position;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function move(Position $position, Grid $grid): bool
    {
        if (!$grid->inBounds($position)) {
            return false;
        }

        $this->position = $position;
        return true;
    }
}
