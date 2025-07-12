<?php

namespace BlueFission\Automata\Memory;

// For non-embedded, string-label-only comparisons — useful in fallback or string-centric memory:
class LevenshteinLabelSimilarityStrategy implements IRecallScoringStrategy
{
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $labelA = $contextA->get('label');
        $labelB = $contextB->get('label');

        if (!$labelA || !$labelB) {
            return 0.0;
        }

        // Exact match shortcut
        if ($labelA === $labelB) {
            return 1.0;
        }

        $distance = levenshtein($labelA, $labelB);
        $maxLength = max(strlen($labelA), strlen($labelB));

        if ($maxLength === 0) {
            return 1.0; // Edge case: both labels are empty
        }

        // Normalize to 0.0 - 1.0 (1.0 = identical, 0.0 = totally different)
        $similarity = 1.0 - ($distance / $maxLength);
        return max(0.0, min(1.0, $similarity));
    }
}