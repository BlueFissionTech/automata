<?php

namespace BlueFission\Automata\Strategy;

use BlueFission\Arr;
use BlueFission\Automata\Analysis\KNearestExplorer;
use BlueFission\Automata\Support\IStructureFactory;
use BlueFission\Automata\Support\StructureFactory;
use BlueFission\DevElation as Dev;
use BlueFission\Num;
use BlueFission\Val;

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

    protected KNearestExplorer $explorer;

    protected IStructureFactory $structures;

    public function __construct(int $k = 3, ?KNearestExplorer $explorer = null, ?IStructureFactory $structures = null)
    {
        $this->k = $k;
        $this->structures = $structures ?? new StructureFactory();
        $this->explorer = $explorer ?? new KNearestExplorer(structures: $this->structures);
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

        $this->trainSamples = $this->structures->values($samples);
        $this->trainTargets = $this->structures->arr($labels)->values()->map(static function ($label) {
            return (float)$label;
        })->val();
        $this->explorer->setData($this->trainSamples);

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

        if (Val::isEmpty($neighbors)) {
            return 0.0;
        }

        $sum = Num::make(0.0);
        $weightSum = Num::make(0.0);

        foreach ($neighbors as $neighbor) {
            $index = $neighbor['index'];
            $distance = $neighbor['distance'];
            $target = $this->trainTargets[$index] ?? 0.0;

            // Invert distance as weight; add small epsilon to avoid division by zero.
            $weight = 1.0 / (1.0 + $distance);
            $sum->plus(Num::make($weight)->times($target)->val());
            $weightSum->plus($weight);
        }

        if ($weightSum->val() === 0.0) {
            $prediction = 0.0;
        } else {
            $prediction = $sum->divide($weightSum->val())->val();
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
        if (Val::isEmpty($this->_testSamples) || Val::isEmpty($this->_testTargets)) {
            $rmse = 0.0;
        } else {
            $n = Arr::count($this->_testSamples);
            if ($n === 0) {
                $rmse = 0.0;
            } else {
                $sumSq = Num::make(0.0);
                foreach ($this->_testSamples as $i => $sample) {
                    $pred = $this->predict($sample);
                    $actual = (float)$this->_testTargets[$i];
                    $sumSq->plus(Num::make($pred)->minus($actual)->pow(2)->val());
                }

                $rmse = $sumSq->divide($n)->sqrt()->val();
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

        $neighbors = $this->explorer->neighbors($features, $k);
        $neighbors = Dev::apply('automata.strategy.knearestregression.neighbors.2', $neighbors);
        Dev::do('automata.strategy.knearestregression.neighbors.action2', ['features' => $features, 'k' => $k, 'neighbors' => $neighbors]);

        return $neighbors;
    }
}

