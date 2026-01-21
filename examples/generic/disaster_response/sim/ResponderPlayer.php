<?php

namespace Examples\DisasterResponse\Sim;

use BlueFission\Automata\GameTheory\Player;

use Examples\DisasterResponse\Sim\Grid;
use Examples\DisasterResponse\Sim\Position;

class ResponderPlayer extends Player
{
    private ?string $lastDecision = null;
    private Position $position;

    public function __construct(string $name, Position $position)
    {
        parent::__construct($name);
        $this->position = $position;
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
