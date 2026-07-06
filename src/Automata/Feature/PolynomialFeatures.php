<?php

namespace BlueFission\Automata\Feature;

use BlueFission\Automata\Support\IStructureFactory;
use BlueFission\Automata\Support\StructureFactory;
use BlueFission\DevElation as Dev;
use BlueFission\Num;

class PolynomialFeatures {
    private $_degree;
    private IStructureFactory $_structures;

    public function __construct($degree = 2, ?IStructureFactory $structures = null) {
        $this->_structures = $structures ?? new StructureFactory();
        $this->_degree = Dev::apply('feature.polynomial.degree', $degree);
        Dev::do('feature.polynomial.construct', ['degree' => $this->_degree]);
    }

    public function transform($data) {
        $data = Dev::apply('feature.polynomial.input', $data);
        $result = $this->_structures->vec();

        foreach ($data as $row) {
            $vectorRow = $this->_structures->vec($row);
            $newRow = $this->_structures->vec();

            for ($i = 0; $i < $vectorRow->count(); $i++) {
                for ($j = $i; $j < $vectorRow->count(); $j++) {
                    if ($i == $j) {
                        for ($d = 1; $d <= $this->_degree; $d++) {
                            $newRow->add(Num::make($vectorRow->get($i))->pow($d)->val());
                        }
                    } else {
                        $newRow->add(Num::make($vectorRow->get($i))->times($vectorRow->get($j))->val());
                    }
                }
            }
            $result->add($newRow);
        }

        $result = Dev::apply('feature.polynomial.output', $result);
        Dev::do('feature.polynomial.complete', ['result' => $result]);
        return $result;
    }
}
