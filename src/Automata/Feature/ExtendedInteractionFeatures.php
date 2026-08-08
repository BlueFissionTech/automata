<?php

namespace BlueFission\Automata\Feature;

use BlueFission\Arr;
use BlueFission\Automata\Support\IStructureFactory;
use BlueFission\Automata\Support\StructureFactory;
use BlueFission\DevElation as Dev;
use BlueFission\Num;

class ExtendedInteractionFeatures {
    private $_maxOrder;
    private IStructureFactory $_structures;

    public function __construct($maxOrder = 3, ?IStructureFactory $structures = null) {
        $this->_structures = $structures ?? new StructureFactory();
        $this->_maxOrder = Dev::apply('feature.extended.order', $maxOrder);
        Dev::do('feature.extended.construct', ['maxOrder' => $this->_maxOrder]);
    }

    public function transform($data) {
        $data = Dev::apply('feature.extended.input', $data);
        $interactionData = $this->_structures->vec();

        foreach ($data as $row) {
            $vectorRow = $this->_structures->vec($row);
            $newRow = $this->_structures->vec($vectorRow->val());

            // Generate combinations for each order level
            for ($order = 2; $order <= $this->_maxOrder; $order++) {
                $this->addAllCombinations($vectorRow, $newRow, $order);
            }

            $interactionData->add($newRow->val());
        }
        $result = Dev::apply('feature.extended.output', $interactionData);
        Dev::do('feature.extended.complete', ['result' => $result]);
        return $result;
    }

    private function addAllCombinations(mixed $vectorRow, mixed $newRow, $order) {
        $indices = range(0, $vectorRow->count() - 1);
        $combinations = $this->getCombinations($indices, $order);

        foreach ($combinations as $combination) {
            $product = Num::make(1);
            foreach ($combination as $index) {
                $product->times($vectorRow->get($index));
            }
            $newRow->add($product->val());
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

        for ($i = $start; $i <= Arr::count($indices) - $k; $i++) {
            $prefix[] = $indices[$i];
            $this->combine($result, $prefix, $indices, $k - 1, $i + 1);
            array_pop($prefix);
        }
    }
}
