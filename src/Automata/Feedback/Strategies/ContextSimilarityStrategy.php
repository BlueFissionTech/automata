<?php

namespace BlueFission\Automata\Feedback\Strategies;

use BlueFission\Automata\Feedback\IAssessmentStrategy;
use BlueFission\Automata\Feedback\Projection;
use BlueFission\Automata\Feedback\Observation;

class ContextSimilarityStrategy implements IAssessmentStrategy
{
    public function score(Projection $projection, Observation $observation): float
    {
        $projectionContext = $projection->context()->all();
        $observationContext = $observation->context()->all();

        if (empty($projectionContext) || empty($observationContext)) {
            return 0.0;
        }

        $sharedKeys = array_intersect(array_keys($projectionContext), array_keys($observationContext));
        if (empty($sharedKeys)) {
            return 0.0;
        }

        $matches = 0;
        foreach ($sharedKeys as $key) {
            if ($projectionContext[$key] === $observationContext[$key]) {
                $matches++;
            }
        }

        return $matches / max(1, count($sharedKeys));
    }

    public function name(): string
    {
        return 'context_similarity';
    }
}
