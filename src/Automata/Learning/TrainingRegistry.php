<?php

namespace BlueFission\Automata\Learning;

/** Implementations may persist observations, but must not mutate snapshots. */
interface TrainingRegistry
{
    public function record(string $class, string $operation, TrainingObservation $observation): void;

    /** @return list<TrainingObservation> */
    public function observations(string $class, string $operation): array;
}
