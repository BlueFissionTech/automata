<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;

// Uses simple associative metadata in the context (e.g., category, type, tag) to boost or diminish similarity.
class SemanticDistanceStrategy implements IRecallScoringStrategy
{
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $sim = (new CosineSimilarityStrategy())->score($vecA, $vecB, $contextA, $contextB);

        $tagA = $contextA->get('tag');
        $tagB = $contextB->get('tag');

        if ($tagA !== null && $tagB !== null && $tagA === $tagB) {
            $sim += 0.1; // boost slightly for same tags
        }

        $sim = min(1.0, $sim); // clamp result
        $sim = Dev::apply('automata.memory.semanticdistancestrategy.score.1', $sim);
        Dev::do('automata.memory.semanticdistancestrategy.score.action1', ['contextA' => $contextA, 'contextB' => $contextB, 'score' => $sim]);

        return $sim;
    }
}
