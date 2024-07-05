<?php

namespace BlueFission\Automata\Feature;

use BlueFission\Vec;

class PolynomialFeatures {
    private $_degree;

    public function __construct($degree = 2) {
        $this->_degree = $degree;
    }

    public function transform($data) {
        $result = new Vec();

        foreach ($data as $row) {
            $vectorRow = new Vec($row);  // Convert array row to Vec
            $newRow = new Vec();

            for ($i = 0; $i < $vectorRow->count(); $i++) {
                for ($j = $i; $j < $vectorRow->count(); $j++) {
                    if ($i == $j) {
                        for ($d = 1; $d <= $this->_degree; $d++) {
                            $newRow->add(pow($vectorRow->get($i), $d));  // Add polynomial term
                        }
                    } else {
                        $newRow->add($vectorRow->get($i) * $vectorRow->get($j));  // Add interaction term
                    }
                }
            }
            $result->add($newRow);
        }

        return $result;
    }
}
