<?php

namespace BlueFission\Automata\Feature\Selection;

use BlueFission\Arr;
use BlueFission\Automata\Support\IStructureFactory;
use BlueFission\Automata\Support\StructureFactory;
use BlueFission\Num;
use BlueFission\Val;

class VarianceThresholdSelector {
    private $_threshold;
    private IStructureFactory $_structures;

    public function __construct($threshold = 0.0, ?IStructureFactory $structures = null) {
        $this->_threshold = $threshold;
        $this->_structures = $structures ?? new StructureFactory();
    }

    public function fitTransform(array $data) {
        if (Val::isEmpty($data)) {
            return [];
        }

        $variances = $this->calculateVariances($data);
        return $this->filterFeatures($data, $variances);
    }

    private function calculateVariances(array $data) {
        $columnCount = Arr::count($data[0] ?? []);
        $means = $this->_structures->fill($columnCount, 0);
        $variances = $this->_structures->fill($columnCount, 0);
        
        foreach ($data as $row) {
            foreach ($row as $i => $value) {
                $means[$i] += $value;
            }
        }
        $rowCount = Arr::count($data);
        $means = $this->_structures->arr($means)->map(function($mean) use ($rowCount) {
            return Num::make($mean)->divide($rowCount)->val();
        })->values()->val();

        foreach ($data as $row) {
            foreach ($row as $i => $value) {
                $variances[$i] += Num::make($value)->minus($means[$i])->pow(2)->val();
            }
        }
        $denominator = Num::max(1, Num::make($rowCount)->minus(1)->val());
        $variances = $this->_structures->arr($variances)->map(function($variance) use ($denominator) {
            return Num::make($variance)->divide($denominator)->val();
        })->values()->val();

        return $variances;
    }

    private function filterFeatures(array $data, array $variances) {
        $filteredData = [];
        foreach ($data as $row) {
            $filteredRow = [];
            foreach ($row as $i => $value) {
                if ($variances[$i] > $this->_threshold) {
                    $filteredRow[] = $value;
                }
            }
            $filteredData[] = $filteredRow;
        }
        return $filteredData;
    }
}
