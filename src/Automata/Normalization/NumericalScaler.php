<?php

namespace BlueFission\Automata\Normalization;

use BlueFission\DevElation as Dev;

class NumericalScaler {
    private $mean = 0;
    private $std = 1;

    public function fit($data) {
        $data = Dev::apply('normalization.scaler.fit_input', $data);
        $this->mean = array_sum($data) / count($data);
        $sumOfSquares = array_sum(array_map(function($item) {
            return pow($item - $this->mean, 2);
        }, $data));
        $this->std = sqrt($sumOfSquares / count($data));
        Dev::do('normalization.scaler.fit', ['mean' => $this->mean, 'std' => $this->std]);
    }

    public function transform($data) {
        $data = Dev::apply('normalization.scaler.transform_input', $data);
        return array_map(function($item) {
            return ($item - $this->mean) / $this->std;
        }, $data);
    }

    public function fitTransform($data) {
        $this->fit($data);
        return $this->transform($data);
    }
}
