<?php

namespace BlueFission\Automata\Feature;

use BlueFission\Vec;

class ExtendedInteractionFeatures {
    private $maxOrder;

    public function __construct($maxOrder = 3) {
        $this->maxOrder = $maxOrder;
    }

    public function transform($data) {
        $interactionData = new Vec();

        foreach ($data as $row) {
            $vectorRow = new Vec($row);
            $newRow = new Vec($vectorRow);  // Start with original features, using Vec

            // Generate combinations for each order level
            for ($order = 2; $order <= $this->maxOrder; $order++) {
                $this->addAllCombinations($vectorRow, $newRow, $order);
            }

            $interactionData->add($newRow);
        }
        return $interactionData;
    }

    private function addAllCombinations(Vec $vectorRow, Vec $newRow, $order) {
        $indices = range(0, $vectorRow->count() - 1);
        $combinations = $this->getCombinations($indices, $order);

        foreach ($combinations as $combination) {
            $product = 1;
            foreach ($combination as $index) {
                $product *= $vectorRow->get($index);
            }
            $newRow->add($product);
        }
    }

    private function getCombinations($indices, $k) {
        $result = [];
        $this->combine($result, [], $indices, $k, 0);
        return $result;
    }

    private function combine(&$result, $prefix, $indices, $k, $start) {
        if ($k == 0) {
            $result[] = $prefix;
            return;
        }

        for ($i = $start; $i <= count($indices) - $k; $i++) {
            $prefix[] = $indices[$i];
            $this->combine($result, $prefix, $indices, $k - 1, $i + 1);
            array_pop($prefix);
        }
    }
}
