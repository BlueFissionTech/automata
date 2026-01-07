<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Context;

// Balances semantic similarity (via cosine) with relevance (via reinforcement or importance weight).
class WeightedCosineSimilarityStrategy implements IRecallScoringStrategy
{
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $base = (new CosineSimilarityStrategy())->score($vecA, $vecB, $contextA, $contextB);

        $weightA = (float)$contextA->get('weight', 1);
        $weightB = (float)$contextB->get('weight', 1);

        $scale = sqrt(max($weightA, 0.0) * max($weightB, 0.0)) ?: 1.0;

        return $base * $scale;
    }
}
