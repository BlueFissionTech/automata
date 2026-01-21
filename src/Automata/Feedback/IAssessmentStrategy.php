<?php

namespace BlueFission\Automata\Feedback;

interface IAssessmentStrategy
{
    public function score(Projection $projection, Observation $observation): float;
    public function name(): string;
}
