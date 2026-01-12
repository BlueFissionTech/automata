<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;

// For non-embedded, string-label-only comparisons - useful in fallback or string-centric memory.
class LevenshteinLabelSimilarityStrategy implements IRecallScoringStrategy
{
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        $labelA = (string)$contextA->get('label', '');
        $labelB = (string)$contextB->get('label', '');

        if ($labelA === '' && $labelB === '') {
            return 1.0;
        }

        // Exact match shortcut
        if ($labelA === $labelB) {
            return 1.0;
        }

        $distance = levenshtein($labelA, $labelB);
        $maxLength = max(strlen($labelA), strlen($labelB));

        if ($maxLength === 0) {
            return 1.0;
        }

        // Normalize to 0.0 - 1.0 (1.0 = identical, 0.0 = totally different)
        $similarity = 1.0 - ($distance / $maxLength);
        $similarity = max(0.0, min(1.0, $similarity));

        $similarity = Dev::apply('automata.memory.levenshteinlabelsimilaritystrategy.score.1', $similarity);
        Dev::do('automata.memory.levenshteinlabelsimilaritystrategy.score.action1', ['labelA' => $labelA, 'labelB' => $labelB, 'score' => $similarity]);

        return $similarity;
    }
}
