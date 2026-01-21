<?php

namespace BlueFission\Automata\Feedback\Strategies;

use BlueFission\Automata\Feedback\IAssessmentStrategy;
use BlueFission\Automata\Feedback\Projection;
use BlueFission\Automata\Feedback\Observation;

class TimeWindowMatchStrategy implements IAssessmentStrategy
{
    public function score(Projection $projection, Observation $observation): float
    {
        if ($projection->isExpired($observation->timestamp())) {
            return 0.0;
        }

        return 1.0;
    }

    public function name(): string
    {
        return 'time_window';
    }
}
