<?php

namespace BlueFission\Automata\Memory;

class CosineSimilarityStrategy implements IRecallScoringStrategy
{
	// The default fallback — compares hashed vectors using cosine similarity.
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;

        for ($i = 0; $i < count($vecA); $i++) {
            $dot += $vecA[$i] * $vecB[$i];
            $magA += $vecA[$i] ** 2;
            $magB += $vecB[$i] ** 2;
        }

        $denom = sqrt($magA) * sqrt($magB);
        return $denom > 0 ? $dot / $denom : 0.0;
    }
}
