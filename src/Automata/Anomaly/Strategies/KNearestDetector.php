<?php

namespace BlueFission\Automata\Anomaly\Strategies;

use BlueFission\Automata\Analysis\KNearestAnomaly;
use BlueFission\Automata\Analysis\KNearestExplorer;
use BlueFission\Automata\Anomaly\Detector;
use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;

class KNearestDetector extends Detector
{
    protected KNearestExplorer $explorer;
    protected KNearestAnomaly $anomaly;
    protected int $k;

    public function __construct(int $k = 3, float $threshold = 0.5)
    {
        parent::__construct($threshold);
        $this->explorer = new KNearestExplorer();
        $this->anomaly = new KNearestAnomaly($this->explorer);
        $this->k = $k;
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $samples = Dev::apply('anomaly.detector.knearest.train.samples', $samples);
        Dev::do('anomaly.detector.knearest.train', ['samples' => $samples]);
        $this->explorer->setData($samples);
        return null;
    }

    public function score($input, Context $context, array $options = []): float
    {
        $features = $this->resolveFeatures($input);
        if (empty($features)) {
            return 0.0;
        }

        $k = isset($options['k']) ? (int)$options['k'] : $this->k;
        $k = max(1, $k);

        return $this->anomaly->score($features, $k);
    }
}
