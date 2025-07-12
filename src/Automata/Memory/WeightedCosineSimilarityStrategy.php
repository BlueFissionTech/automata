<?php

namespace BlueFission\Automata\Memory;

// Balances semantic similarity (via cosine) with relevance (via reinforcement weight).
class WeightedCosineSimilarityStrategy implements IRecallScoringStrategy
{
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $similarity = cosine_similarity($vecA, $vecB);
        $weightA = $contextA->get('weight', 1);
        $weightB = $contextB->get('weight', 1);
        $scale = sqrt($weightA * $weightB);

        return $similarity * $scale;
    }
}
