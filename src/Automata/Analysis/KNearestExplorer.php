<?php

namespace BlueFission\Automata\Analysis;

use BlueFission\Obj;

/**
 * KNearestExplorer
 *
 * Generic nearest-neighbor search helper built around a
 * set of feature vectors and optional IDs. Intended to be
 * used both by strategies (e.g., KNearestRegression) and
 * by higher-level services that want neighbor explanations.
 */
class KNearestExplorer extends Obj
{
    /** @var array<int,array<float|int>> */
    protected array $samples = [];

    /** @var array<int,string|int> */
    protected array $ids = [];

    /**
     * @param array<int,array<float|int>> $samples
     * @param array<int,string|int>|null  $ids
     */
    public function __construct(array $samples = [], ?array $ids = null)
    {
        parent::__construct();
        $this->samples = array_values($samples);
        $this->ids = $ids !== null ? array_values($ids) : range(0, count($samples) - 1);
    }

    /**
     * Update the underlying dataset.
     *
     * @param array<int,array<float|int>> $samples
     * @param array<int,string|int>|null  $ids
     */
    public function setData(array $samples, ?array $ids = null): void
    {
        $this->samples = array_values($samples);
        $this->ids = $ids !== null ? array_values($ids) : range(0, count($samples) - 1);
    }

    /**
     * Find top-k nearest neighbors of the given feature vector.
     *
     * @param array<int,float|int> $features
     * @param int                  $k
     * @return array<int,array{id:string|int,index:int,distance:float}>
     */
    public function neighbors(array $features, int $k): array
    {
        $distances = [];

        foreach ($this->samples as $index => $sample) {
            $distances[] = [
                'id' => $this->ids[$index] ?? $index,
                'index' => $index,
                'distance' => $this->euclideanDistance($features, $sample),
            ];
        }

        usort($distances, function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return array_slice($distances, 0, max(0, $k));
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

