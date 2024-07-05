<?php

namespace BlueFission\Automata\Feature\Selection;

class VarianceThresholdSelector {
    private $_threshold;

    public function __construct($threshold = 0.0) {
        $this->_threshold = $threshold;
    }

    public function fitTransform(array $data) {
        $variances = $this->calculateVariances($data);
        return $this->filterFeatures($data, $variances);
    }

    private function calculateVariances(array $data) {
        $means = array_fill(0, count($data[0]), 0);
        $variances = array_fill(0, count($data[0]), 0);
        
        foreach ($data as $row) {
            foreach ($row as $i => $value) {
                $means[$i] += $value;
            }
        }
        $means = array_map(function($mean) use ($data) {
            return $mean / count($data);
        }, $means);

        foreach ($data as $row) {
            foreach ($row as $i => $value) {
                $variances[$i] += pow($value - $means[$i], 2);
            }
        }
        $variances = array_map(function($variance) use ($data) {
            return $variance / (count($data) - 1);
        }, $variances);

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
