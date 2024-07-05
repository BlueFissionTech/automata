<?php

namespace BlueFission\Automata\Normalization;

class NumericalScaler {
    private $mean = 0;
    private $std = 1;

    public function fit($data) {
        $this->mean = array_sum($data) / count($data);
        $sumOfSquares = array_sum(array_map(function($item) {
            return pow($item - $this->mean, 2);
        }, $data));
        $this->std = sqrt($sumOfSquares / count($data));
    }

    public function transform($data) {
        return array_map(function($item) {
            return ($item - $this->mean) / $this->std;
        }, $data);
    }

    public function fitTransform($data) {
        $this->fit($data);
        return $this->transform($data);
    }
}
