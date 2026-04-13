<?php

namespace Examples\DisasterResponse\Sim;

use BlueFission\Automata\GameTheory\Player;

use Examples\DisasterResponse\Sim\Grid;
use Examples\DisasterResponse\Sim\Position;

class ResponderPlayer extends Player
{
    private ?string $lastDecision = null;
    private Position $gridPosition;

    public function __construct(string $name, Position $position)
    {
        parent::__construct($name);
        $this->gridPosition = $position;
        parent::position($position->toArray());
    }

    public function decide()
    {
        $decision = parent::decide();
        $this->lastDecision = is_string($decision) ? $decision : null;

        return $decision;
    }

    public function lastDecision(): ?string
    {
        return $this->lastDecision;
    }

    public function position(mixed $position = null): mixed
    {
        if (func_num_args() === 0) {
            return $this->gridPosition;
        }

        if (!$position instanceof Position) {
            throw new \InvalidArgumentException('ResponderPlayer position expects a Position instance.');
        }

        $this->gridPosition = $position;
        parent::position($position->toArray());

        return $this;
    }

    public function move(Position $position, Grid $grid): bool
    {
        if (!$grid->inBounds($position)) {
            return false;
        }

        $this->position($position);
        return true;
    }
}
