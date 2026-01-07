<?php

namespace BlueFission\Automata\Analysis;

use BlueFission\Obj;

/**
 * KNearestAnomaly
 *
 * Simple anomaly scoring based on K-nearest neighbor distances.
 * Higher average distance to neighbors implies a more anomalous
 * point relative to the reference dataset.
 */
class KNearestAnomaly extends Obj
{
    protected KNearestExplorer $explorer;

    public function __construct(KNearestExplorer $explorer)
    {
        parent::__construct();
        $this->explorer = $explorer;
    }

    /**
     * Compute an anomaly score for a point as the average distance
     * to its K nearest neighbors.
     *
     * @param array<int,float|int> $features
     */
    public function score(array $features, int $k): float
    {
        $neighbors = $this->explorer->neighbors($features, $k);
        if (empty($neighbors)) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($neighbors as $neighbor) {
            $sum += $neighbor['distance'];
        }

        return $sum / count($neighbors);
    }

    /**
     * Decide if a point is anomalous given a threshold.
     */
    public function isAnomalous(array $features, int $k, float $threshold): bool
    {
        return $this->score($features, $k) > $threshold;
    }
}

