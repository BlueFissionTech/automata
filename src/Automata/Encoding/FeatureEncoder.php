<?php

namespace BlueFission\Automata\Encoding;

use BlueFission\Arr;
use BlueFission\Vec;
use BlueFission\DevElation as Dev;

class FeatureEncoder {
    private $_numericalFeaturesIndices;
    private $_categoricalFeaturesIndices;
    private $_minMaxData;
    private $_categories;

    public function __construct($numericalFeaturesIndices, $categoricalFeaturesIndices) {
        $this->_numericalFeaturesIndices = Dev::apply('encoding.feature.numerical', $numericalFeaturesIndices);
        $this->_categoricalFeaturesIndices = Dev::apply('encoding.feature.categorical', $categoricalFeaturesIndices);
        $this->_minMaxData = new Arr([]);
        $this->_categories = new Arr([]);
        Dev::do('encoding.feature.construct', [
            'numerical' => $this->_numericalFeaturesIndices,
            'categorical' => $this->_categoricalFeaturesIndices,
        ]);
    }

    public function fit($data) {
        $data = Dev::apply('encoding.feature.fit_input', $data);
        foreach ($this->_numericalFeaturesIndices as $index) {
            $column = array_column($data, $index);
            $min = min($column);
            $max = max($column);
            $this->_minMaxData->set($index, [$min, $max]);
        }

        foreach ($this->_categoricalFeaturesIndices as $index) {
            $column = array_column($data, $index);
            $this->_categories->set($index, array_values(array_unique($column)));
        }
        Dev::do('encoding.feature.fitted', ['minMax' => $this->_minMaxData, 'categories' => $this->_categories]);
    }

    public function transform($data) {
        $data = Dev::apply('encoding.feature.transform_input', $data);
        $transformedData = [];
        foreach ($data as $row) {
            $newRow = new Vec();
            foreach ($row as $i => $value) {
                if (in_array($i, $this->_numericalFeaturesIndices)) {
                    $minMax = $this->_minMaxData->get($i);
                    if (is_array($minMax) && count($minMax) === 2) {
                        [$min, $max] = $minMax;
                        $newRow->add(($value - $min) / ($max - $min));
                    } else {
                        $newRow->add($value);
                    }
                } elseif (in_array($i, $this->_categoricalFeaturesIndices)) {
                    $categories = $this->_categories->get($i);
                    if (!is_array($categories)) {
                        $categories = [];
                    }
                    foreach ($categories as $category) {
                        $newRow->add(($value == $category) ? 1 : 0);
                    }
                } else {
                    $newRow->add($value);  // Unchanged other features
                }
            }
            $transformedData[] = $newRow;
        }
        $result = Dev::apply('encoding.feature.transform_output', $transformedData);
        Dev::do('encoding.feature.transformed', ['result' => $result]);
        return $result;
    }
}
