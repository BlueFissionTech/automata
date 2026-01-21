<?php

namespace BlueFission\Automata\Feedback\Strategies;

use BlueFission\Automata\Feedback\IAssessmentStrategy;
use BlueFission\Automata\Feedback\Projection;
use BlueFission\Automata\Feedback\Observation;

class LabelOverlapStrategy implements IAssessmentStrategy
{
    public function score(Projection $projection, Observation $observation): float
    {
        $projectionTags = $projection->tags();
        $observationTags = $observation->tags();

        if (empty($projectionTags) || empty($observationTags)) {
            return 0.0;
        }

        $overlap = array_intersect($projectionTags, $observationTags);
        $score = count($overlap) / max(1, count($projectionTags));

        return (float)$score;
    }

    public function name(): string
    {
        return 'label_overlap';
    }
}
