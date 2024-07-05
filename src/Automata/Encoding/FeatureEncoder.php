<?php

namespace BlueFission\Automata\Encoding;

use BlueFission\Vec;

class FeatureEncoder {
    private $_numericalFeaturesIndices;
    private $_categoricalFeaturesIndices;
    private $_minMaxData;
    private $_categories;

    public function __construct($numericalFeaturesIndices, $categoricalFeaturesIndices) {
        $this->_numericalFeaturesIndices = $numericalFeaturesIndices;
        $this->_categoricalFeaturesIndices = $categoricalFeaturesIndices;
        $this->_minMaxData = new Vec();
        $this->_categories = new Vec();
    }

    public function fit($data) {
        foreach ($this->_numericalFeaturesIndices as $index) {
            $column = array_column($data, $index);
            $min = min($column);
            $max = max($column);
            $this->_minMaxData->add([$min, $max]);
        }

        foreach ($this->_categoricalFeaturesIndices as $index) {
            $column = array_column($data, $index);
            $this->_categories->add(array_unique($column));
        }
    }

    public function transform($data) {
        $transformedData = [];
        foreach ($data as $row) {
            $newRow = new Vec();
            foreach ($row as $i => $value) {
                if (in_array($i, $this->_numericalFeaturesIndices)) {
                    list($min, $max) = $this->_minMaxData->get($i);
                    $newRow->add(($value - $min) / ($max - $min));
                } elseif (in_array($i, $this->_categoricalFeaturesIndices)) {
                    foreach ($this->_categories->get($i) as $category) {
                        $newRow->add(($value == $category) ? 1 : 0);
                    }
                } else {
                    $newRow->add($value);  // Unchanged other features
                }
            }
            $transformedData[] = $newRow;
        }
        return $transformedData;
    }
}
