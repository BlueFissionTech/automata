<?php

namespace BlueFission\Automata\Simulation;

use BlueFission\Arr;

/**
 * Simulation
 *
 * Generic discrete-time simulation loop. Maintains a shared world state and a
 * collection of ISimulatable entities, and steps them forward for a fixed
 * number of ticks.
 *
 * The world state is stored in a DevElation Arr for consistency with the rest
 * of the library, but callers interact with it as plain arrays.
 */
class Simulation
{
    /** @var int */
    private int $_ticks;

    /** @var Arr<ISimulatable> */
    private Arr $_entities;

    public function __construct(int $ticks = 1)
    {
        $this->_ticks = max(0, $ticks);
        $this->_entities = new Arr([]);
    }

    /**
     * Add a simulatable entity to the simulation.
     */
    public function addEntity(ISimulatable $entity): void
    {
        $entities = $this->_entities->val();
        $entities[] = $entity;
        $this->_entities->val($entities);
    }

    /**
     * Run the simulation from an initial world state.
     *
     * @param array $initialState
     * @return array<int,array> Per-tick snapshots of the world state.
     */
    public function run(array $initialState = []): array
    {
        $world = new Arr($initialState);
        $log   = [];

        $entities = $this->_entities->val();

        for ($tick = 0; $tick < $this->_ticks; $tick++) {
            $state = $world->val();

            foreach ($entities as $entity) {
                if ($entity instanceof ISimulatable) {
                    $entity->step($tick, $state);
                }
            }

            $state['tick'] = $tick;
            $world->val($state);
            $log[] = $state;
        }

        return $log;
    }
}

