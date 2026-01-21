<?php

namespace BlueFission\Automata\Classification;

use BlueFission\Automata\Context;

interface IClassifier
{
    public function train(array $samples, array $labels, float $testSize = 0.2);
    public function classify($input, Context $context, array $options = []): Result;
    public function accuracy(): float;
    public function saveModel(string $path): bool;
    public function loadModel(string $path): bool;
}
