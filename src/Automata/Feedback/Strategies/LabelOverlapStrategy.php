<?php

namespace BlueFission\Automata\Feedback\Strategies;

use BlueFission\Arr;
use BlueFission\Automata\Feedback\IAssessmentStrategy;
use BlueFission\Automata\Feedback\Projection;
use BlueFission\Automata\Feedback\Observation;
use BlueFission\Num;

class LabelOverlapStrategy implements IAssessmentStrategy
{
    public function score(Projection $projection, Observation $observation): float
    {
        $projectionTags = $projection->tags();
        $observationTags = $observation->tags();

        if (empty($projectionTags) || empty($observationTags)) {
            return 0.0;
        }

        $overlap = Arr::intersect($projectionTags, $observationTags);
        $score = count($overlap) / Num::max(1, count($projectionTags));

        return (float)$score;
    }

    public function name(): string
    {
        return 'label_overlap';
    }
}
