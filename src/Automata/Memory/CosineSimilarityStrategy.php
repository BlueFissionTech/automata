<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Context;

class CosineSimilarityStrategy implements IRecallScoringStrategy
{
    /**
     * Default similarity: cosine over hashed numeric vectors.
     */
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;

        $length = min(count($vecA), count($vecB));

        for ($i = 0; $i < $length; $i++) {
            $a = (float)($vecA[$i] ?? 0);
            $b = (float)($vecB[$i] ?? 0);

            $dot += $a * $b;
            $magA += $a ** 2;
            $magB += $b ** 2;
        }

        $denom = sqrt($magA) * sqrt($magB);

        return $denom > 0.0 ? $dot / $denom : 0.0;
    }
}
