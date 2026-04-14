<?php

namespace BlueFission\Automata\Media\Processing;

interface ITrainable
{
    public function train(array $samples, array $labels = [], array $options = []): void;
}
