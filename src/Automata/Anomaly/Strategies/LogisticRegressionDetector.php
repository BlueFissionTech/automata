<?php

namespace BlueFission\Automata\Anomaly\Strategies;

use Phpml\Classification\Linear\LogisticRegression;

class LogisticRegressionDetector extends PhpmlClassifierDetector
{
    public function __construct(
        int $maxIterations = 500,
        bool $normalizeInputs = true,
        int $trainingType = LogisticRegression::CONJUGATE_GRAD_TRAINING,
        string $cost = 'log',
        string $penalty = 'L2',
        string $anomalyLabel = 'anomaly',
        float $threshold = 0.5
    ) {
        $classifier = new LogisticRegression($maxIterations, $normalizeInputs, $trainingType, $cost, $penalty);
        parent::__construct($classifier, $anomalyLabel, $threshold);
    }
}
