<?php

namespace BlueFission\Automata\Anomaly\Strategies;

use BlueFission\Automata\Anomaly\Detector;
use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;
use Phpml\Clustering\KMeans;
use Phpml\Clustering\KMeans\Space;

class KMeansDetector extends Detector
{
    protected int $clusters;
    protected int $initialization;
    protected array $centroids = [];
    protected float $maxDistance = 1.0;

    public function __construct(int $clusters = 3, int $initialization = KMeans::INIT_KMEANS_PLUS_PLUS, float $threshold = 0.5)
    {
        parent::__construct($threshold);
        $this->clusters = $clusters;
        $this->initialization = $initialization;
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $samples = Dev::apply('anomaly.detector.kmeans.train.samples', $samples);
        Dev::do('anomaly.detector.kmeans.train', ['samples' => $samples]);

        if (empty($samples)) {
            $this->centroids = [];
            $this->maxDistance = 1.0;
            return null;
        }

        $space = new Space(count(reset($samples)));
        foreach ($samples as $index => $sample) {
            $space->addPoint($sample, $index);
        }

        $this->centroids = [];
        foreach ($space->cluster($this->clusters, $this->initialization) as $cluster) {
            $data = $cluster->toArray();
            if (isset($data['centroid']) && is_array($data['centroid'])) {
                $this->centroids[] = $data['centroid'];
            }
        }

        $this->maxDistance = $this->computeMaxDistance($samples);
        $centroidMax = $this->computeMaxCentroidDistance();
        if ($centroidMax > $this->maxDistance) {
            $this->maxDistance = $centroidMax;
        }
        if ($this->maxDistance <= 0.0) {
            $this->maxDistance = 1.0;
        }

        return null;
    }

    public function score($input, Context $context, array $options = []): float
    {
        $features = $this->resolveFeatures($input);
        if (empty($features) || empty($this->centroids)) {
            return 0.0;
        }

        $distance = $this->nearestCentroidDistance($features);
        return min(1.0, $distance / $this->maxDistance);
    }

    protected function nearestCentroidDistance(array $sample): float
    {
        $min = null;
        foreach ($this->centroids as $centroid) {
            $dist = $this->euclideanDistance($sample, $centroid);
            if ($min === null || $dist < $min) {
                $min = $dist;
            }
        }

        return $min ?? 0.0;
    }

    protected function computeMaxDistance(array $samples): float
    {
        $max = 0.0;
        foreach ($samples as $sample) {
            $distance = $this->nearestCentroidDistance($sample);
            if ($distance > $max) {
                $max = $distance;
            }
        }

        return $max;
    }

    protected function computeMaxCentroidDistance(): float
    {
        $max = 0.0;
        $count = count($this->centroids);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $distance = $this->euclideanDistance($this->centroids[$i], $this->centroids[$j]);
                if ($distance > $max) {
                    $max = $distance;
                }
            }
        }

        return $max;
    }

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
