<?php

namespace BlueFission\Automata\Learning;

/** A strategy owns its model; external-model adapters implement this contract. */
interface TrainingStrategy
{
    /** @param list<TrainingObservation> $observations */
    public function train(array $observations): void;

    public function predict(array $input, array $context): TrainingPrediction;
}
