<?php

namespace BlueFission\Automata\Anomaly\Strategies;

use Phpml\Classification\Ensemble\RandomForest;

class RandomForestDetector extends PhpmlClassifierDetector
{
    public function __construct(
        int $trees = 100,
        string $anomalyLabel = 'anomaly',
        float $threshold = 0.5
    ) {
        parent::__construct(new RandomForest($trees), $anomalyLabel, $threshold);
    }
}
