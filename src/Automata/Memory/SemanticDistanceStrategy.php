<?php

namespace BlueFission\Automata\Memory;

// Uses simple associative metadata in the context (e.g., category, type, tag) to boost or diminish similarity — useful for implicit logical grouping.
class SemanticDistanceStrategy implements IRecallScoringStrategy
{
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $sim = (new CosineSimilarityStrategy())->score($vecA, $vecB, $contextA, $contextB);

        $tagA = $contextA->get('tag');
        $tagB = $contextB->get('tag');

        if ($tagA && $tagB && $tagA === $tagB) {
            $sim += 0.1; // boost slightly for same tags
        }

        return min(1.0, $sim); // clamp result
    }
}
