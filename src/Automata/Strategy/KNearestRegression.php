<?php

namespace BlueFission\Automata\Strategy;

use BlueFission\DevElation as Dev;

/**
 * KNearestRegression
 *
 * Simple KNN-style regressor implemented without external
 * dependencies. Uses Euclidean distance to select the K
 * nearest neighbors and predicts a continuous target as
 * the (optionally weighted) average of neighbor targets.
 *
 * This is useful for predicting quantities such as ETAs,
 * loads, or costs from feature vectors.
 */
class KNearestRegression extends Strategy
{
    /** @var array<int,array<float|int>> */
    protected array $trainSamples = [];

    /** @var array<int,float> */
    protected array $trainTargets = [];

    protected int $k;

    public function __construct(int $k = 3)
    {
        $this->k = $k;
    }

    /**
     * Train the regressor with samples and continuous labels.
     *
     * @param array $samples array<int,array<float|int>>
     * @param array $labels  array<int,float|int>
     * @param float $testSize fraction for test split (not used in this simple implementation)
     */
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $samples = Dev::apply('automata.strategy.knearestregression.train.1', $samples);
        $labels  = Dev::apply('automata.strategy.knearestregression.train.2', $labels);
        Dev::do('automata.strategy.knearestregression.train.action1', ['samples' => $samples, 'labels' => $labels]);

        $this->trainSamples = array_values($samples);
        $this->trainTargets = array_map('floatval', array_values($labels));

        // For compatibility with Strategy, set test sets equal to train sets.
        $this->_testSamples = $this->trainSamples;
        $this->_testTargets = $this->trainTargets;
    }

    /**
     * Predict a continuous value for the given feature vector.
     *
     * @param mixed $input feature vector (array)
     * @return float
     */
    public function predict($input)
    {
        $input = Dev::apply('automata.strategy.knearestregression.predict.1', $input);
        Dev::do('automata.strategy.knearestregression.predict.action1', ['input' => $input]);

        $features = (array)$input;
        $neighbors = $this->neighbors($features, $this->k);

        if (empty($neighbors)) {
            return 0.0;
        }

        $sum = 0.0;
        $weightSum = 0.0;

        foreach ($neighbors as $neighbor) {
            $index = $neighbor['index'];
            $distance = $neighbor['distance'];
            $target = $this->trainTargets[$index] ?? 0.0;

            // Invert distance as weight; add small epsilon to avoid division by zero.
            $weight = 1.0 / (1.0 + $distance);
            $sum += $weight * $target;
            $weightSum += $weight;
        }

        if ($weightSum === 0.0) {
            $prediction = 0.0;
        } else {
            $prediction = $sum / $weightSum;
        }

        $prediction = Dev::apply('automata.strategy.knearestregression.predict.2', $prediction);
        Dev::do('automata.strategy.knearestregression.predict.action2', ['input' => $input, 'prediction' => $prediction]);

        return $prediction;
    }

    /**
     * Compute RMSE on the current test set.
     */
    public function accuracy(): float
    {
        if (empty($this->_testSamples) || empty($this->_testTargets)) {
            $rmse = 0.0;
        } else {
            $n = count($this->_testSamples);
            if ($n === 0) {
                $rmse = 0.0;
            } else {
                $sumSq = 0.0;
                foreach ($this->_testSamples as $i => $sample) {
                    $pred = $this->predict($sample);
                    $actual = (float)$this->_testTargets[$i];
                    $sumSq += ($pred - $actual) ** 2;
                }

                $mse = $sumSq / $n;
                $rmse = sqrt($mse);
            }
        }

        $rmse = Dev::apply('automata.strategy.knearestregression.accuracy.1', $rmse);
        Dev::do('automata.strategy.knearestregression.accuracy.action1', ['rmse' => $rmse]);

        return $rmse;
    }

    /**
     * Return top-k neighbors (index + distance) for the given feature vector.
     *
     * @param array<int,float|int> $features
     * @param int                  $k
     * @return array<int,array{index:int,distance:float}>
     */
    public function neighbors(array $features, int $k): array
    {
        $features = Dev::apply('automata.strategy.knearestregression.neighbors.1', $features);
        Dev::do('automata.strategy.knearestregression.neighbors.action1', ['features' => $features, 'k' => $k]);

        $distances = [];

        foreach ($this->trainSamples as $index => $sample) {
            $distances[] = [
                'index' => $index,
                'distance' => $this->euclideanDistance($features, $sample),
            ];
        }

        usort($distances, function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        $neighbors = array_slice($distances, 0, max(0, $k));
        $neighbors = Dev::apply('automata.strategy.knearestregression.neighbors.2', $neighbors);
        Dev::do('automata.strategy.knearestregression.neighbors.action2', ['features' => $features, 'k' => $k, 'neighbors' => $neighbors]);

        return $neighbors;
    }

    /**
     * Euclidean distance between two vectors.
     *
     * @param array<int,float|int> $a
     * @param array<int,float|int> $b
     */
    protected function euclideanDistance(array $a, array $b): float
    {
        $len = max(count($a), count($b));
        $sum = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $v1 = (float)($a[$i] ?? 0.0);
            $v2 = (float)($b[$i] ?? 0.0);
            $diff = $v1 - $v2;
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }
}

