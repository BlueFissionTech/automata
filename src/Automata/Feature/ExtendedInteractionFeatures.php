<?php

namespace BlueFission\Automata\Feature;

use BlueFission\Vec;
use BlueFission\DevElation as Dev;

class ExtendedInteractionFeatures {
    private $_maxOrder;

    public function __construct($maxOrder = 3) {
        $this->_maxOrder = Dev::apply('feature.extended.order', $maxOrder);
        Dev::do('feature.extended.construct', ['maxOrder' => $this->_maxOrder]);
    }

    public function transform($data) {
        $data = Dev::apply('feature.extended.input', $data);
        $interactionData = new Vec();

        foreach ($data as $row) {
            $vectorRow = new Vec($row);
            $newRow = new Vec($vectorRow);  // Start with original features, using Vec

            // Generate combinations for each order level
            for ($order = 2; $order <= $this->_maxOrder; $order++) {
                $this->addAllCombinations($vectorRow, $newRow, $order);
            }

            $interactionData->add($newRow);
        }
        $result = Dev::apply('feature.extended.output', $interactionData);
        Dev::do('feature.extended.complete', ['result' => $result]);
        return $result;
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
