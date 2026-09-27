<?php

namespace BlueFission\Automata\Learning;

interface ITrainingAdapter
{
    public function id(): string;
    public function version(): string;
    /** @return iterable<TrainingExample> Empty when this experience is unsuitable. */
    public function project(Experience $experience): iterable;
}
