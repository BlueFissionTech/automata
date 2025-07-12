<?php

namespace BlueFission\Automata\Memory;

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
        $sim = cosine_similarity($vecA, $vecB);

        $now = time();
        $timeA = $contextA->get('timestamp', $now);
        $timeB = $contextB->get('timestamp', $now);

        $ageA = $now - $timeA;
        $ageB = $now - $timeB;

        $decayA = exp(-$ageA / $this->halfLife);
        $decayB = exp(-$ageB / $this->halfLife);

        return $sim * sqrt($decayA * $decayB);
    }
}
