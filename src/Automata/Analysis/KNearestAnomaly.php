<?php

namespace BlueFission\Automata\Analysis;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Val;
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
        if (Val::isEmpty($neighbors)) {
            return 0.0;
        }

        $sum = Num::make(0.0);
        foreach ($neighbors as $neighbor) {
            $sum->plus($neighbor['distance']);
        }

        return $sum->divide(Arr::count($neighbors))->val();
    }

    /**
     * Decide if a point is anomalous given a threshold.
     */
    public function isAnomalous(array $features, int $k, float $threshold): bool
    {
        return $this->score($features, $k) > $threshold;
    }
}

