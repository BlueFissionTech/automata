<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;

// Balances semantic similarity (via cosine) with relevance (via reinforcement or importance weight).
class WeightedCosineSimilarityStrategy implements IRecallScoringStrategy
{
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $base = (new CosineSimilarityStrategy())->score($vecA, $vecB, $contextA, $contextB);

        $weightA = (float)$contextA->get('weight', 1);
        $weightB = (float)$contextB->get('weight', 1);

        $scale = sqrt(max($weightA, 0.0) * max($weightB, 0.0)) ?: 1.0;
        $score = $base * $scale;

        $score = Dev::apply('automata.memory.weightedcosinesimilaritystrategy.score.1', $score);
        Dev::do('automata.memory.weightedcosinesimilaritystrategy.score.action1', ['contextA' => $contextA, 'contextB' => $contextB, 'score' => $score]);

        return $score;
    }
}
