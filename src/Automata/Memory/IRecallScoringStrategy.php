<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Context;

/**
 * Strategy interface for scoring the relatedness of two memories,
 * given their hashed vectors and contextual payloads.
 */
interface IRecallScoringStrategy
{
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float;
}
