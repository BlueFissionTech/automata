<?php

namespace BlueFission\Automata\Normalization;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Val;
use BlueFission\DevElation as Dev;

class NumericalScaler {
    private $mean = 0;
    private $std = 1;

    public function fit($data) {
        $data = Dev::apply('normalization.scaler.fit_input', $data);
        if (Val::isEmpty($data)) {
            $this->mean = 0;
            $this->std = 1;
            Dev::do('normalization.scaler.fit', ['mean' => $this->mean, 'std' => $this->std]);
            return;
        }

        $count = Arr::count($data);
        $this->mean = Num::make(array_sum($data))->divide($count)->val();
        $sumOfSquares = array_sum(Arr::make($data)->map(function($item) {
            return Num::make($item)->minus($this->mean)->pow(2)->val();
        })->val());
        $this->std = Num::make($sumOfSquares)->divide($count)->sqrt()->val();
        if ($this->std == 0) {
            $this->std = 1;
        }
        Dev::do('normalization.scaler.fit', ['mean' => $this->mean, 'std' => $this->std]);
    }

    public function transform($data) {
        $data = Dev::apply('normalization.scaler.transform_input', $data);
        return Arr::make($data)->map(function($item) {
            return Num::make($item)->minus($this->mean)->divide($this->std)->val();
        })->values()->val();
    }

    public function fitTransform($data) {
        $this->fit($data);
        return $this->transform($data);
    }
}
