<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Context;

// Adds a time-decay penalty based on recency, helping models prefer more recent associations.
class TemporalDecaySimilarityStrategy implements IRecallScoringStrategy
{
    protected float $halfLife; // in seconds

    public function __construct(float $halfLife = 3600)
    {
        $this->halfLife = $halfLife;
    }

    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $base = (new CosineSimilarityStrategy())->score($vecA, $vecB, $contextA, $contextB);

        $now = time();
        $timeA = (int)$contextA->get('timestamp', $now);
        $timeB = (int)$contextB->get('timestamp', $now);

        $ageA = max($now - $timeA, 0);
        $ageB = max($now - $timeB, 0);

        $decayA = exp(-$ageA / $this->halfLife);
        $decayB = exp(-$ageB / $this->halfLife);

        return $base * sqrt($decayA * $decayB);
    }
}
