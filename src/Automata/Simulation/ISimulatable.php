<?php

namespace BlueFission\Automata\Simulation;

/**
 * ISimulatable
 *
 * Minimal contract for simulation entities that can advance a shared world
 * state one discrete tick at a time.
 */
interface ISimulatable
{
    /**
     * Advance the entity and mutate the shared world state for this tick.
     *
     * @param int   $tick       Current tick index (0-based).
     * @param array &$worldState Shared, mutable world state array.
     */
    public function step(int $tick, array &$worldState): void;
}

