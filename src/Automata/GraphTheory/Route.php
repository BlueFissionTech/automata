<?php

namespace BlueFission\Automata\GraphTheory;

use BlueFission\Obj;

/**
 * Route
 *
 * Represents a planned route through the graph along with a
 * simple scalar cost (e.g., time + risk weighting).
 */
class Route extends Obj
{
    /** @var string[] */
    protected array $path = [];

    /** @var float|int */
    protected $cost = 0;

    /**
     * @param string[]   $path
     * @param float|int  $cost
     */
    public function __construct(array $path, $cost)
    {
        parent::__construct();
        $this->path = $path;
        $this->cost = $cost;
    }

    /**
     * Get the node names that make up this route.
     *
     * @return string[]
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * Get the scalar cost assigned to this route.
     *
     * @return float|int
     */
    public function getCost()
    {
        return $this->cost;
    }
}

