<?php

namespace BlueFission\Automata\Analysis;

use BlueFission\Arr;
use BlueFission\Automata\Support\IStructureFactory;
use BlueFission\Automata\Support\StructureFactory;
use BlueFission\Num;
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

    protected IStructureFactory $structures;

    /**
     * @param array<int,array<float|int>> $samples
     * @param array<int,string|int>|null  $ids
     */
    public function __construct(array $samples = [], ?array $ids = null, ?IStructureFactory $structures = null)
    {
        parent::__construct();
        $this->structures = $structures ?? new StructureFactory();
        $this->samples = $this->structures->values($samples);
        $this->ids = $ids !== null
            ? $this->structures->values($ids)
            : $this->defaultIds($samples);
    }

    /**
     * Update the underlying dataset.
     *
     * @param array<int,array<float|int>> $samples
     * @param array<int,string|int>|null  $ids
     */
    public function setData(array $samples, ?array $ids = null): void
    {
        $this->samples = $this->structures->values($samples);
        $this->ids = $ids !== null
            ? $this->structures->values($ids)
            : $this->defaultIds($samples);
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

        return $this->structures->arr($distances)->sort(function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        })->slice(0, Num::make($k)->max(0))->val();
    }

    protected function euclideanDistance(array $a, array $b): float
    {
        $len = Num::make(Arr::count($a))->max(Arr::count($b));
        $sum = Num::make(0.0);
        for ($i = 0; $i < $len; $i++) {
            $v1 = (float)($a[$i] ?? 0.0);
            $v2 = (float)($b[$i] ?? 0.0);
            $diff = Num::make($v1)->minus($v2)->val();
            $sum->plus(Num::make($diff)->times($diff)->val());
        }
        return $sum->sqrt()->val();
    }

    /**
     * @param array<int,array<float|int>> $samples
     * @return array<int,int>
     */
    private function defaultIds(array $samples): array
    {
        $count = Arr::count($samples);

        return $count > 0 ? range(0, $count - 1) : [];
    }
}

