<?php

namespace BlueFission\Automata\Memory;

interface RecallScoringStrategy {
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float;
}