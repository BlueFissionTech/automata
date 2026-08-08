<?php

namespace BlueFission\Automata\Encoding;

use BlueFission\Arr;
use BlueFission\Automata\Support\IStructureFactory;
use BlueFission\Automata\Support\StructureFactory;
use BlueFission\Num;
use BlueFission\DevElation as Dev;

class FeatureEncoder {
    private $_numericalFeaturesIndices;
    private $_categoricalFeaturesIndices;
    private $_minMaxData;
    private $_categories;
    private IStructureFactory $_structures;

    public function __construct($numericalFeaturesIndices, $categoricalFeaturesIndices, ?IStructureFactory $structures = null) {
        $this->_structures = $structures ?? new StructureFactory();
        $this->_numericalFeaturesIndices = Dev::apply('encoding.feature.numerical', $numericalFeaturesIndices);
        $this->_categoricalFeaturesIndices = Dev::apply('encoding.feature.categorical', $categoricalFeaturesIndices);
        $this->_minMaxData = $this->_structures->arr();
        $this->_categories = $this->_structures->arr();
        Dev::do('encoding.feature.construct', [
            'numerical' => $this->_numericalFeaturesIndices,
            'categorical' => $this->_categoricalFeaturesIndices,
        ]);
    }

    public function fit($data) {
        $data = Dev::apply('encoding.feature.fit_input', $data);
        foreach ($this->_numericalFeaturesIndices as $index) {
            $column = array_column($data, $index);
            $columnValues = $this->_structures->values($column);
            $min = min($columnValues);
            $max = max($columnValues);
            $this->_minMaxData->set($index, [$min, $max]);
        }

        foreach ($this->_categoricalFeaturesIndices as $index) {
            $column = array_column($data, $index);
            $this->_categories->set($index, $this->_structures->arr($column)->unique()->values()->val());
        }
        Dev::do('encoding.feature.fitted', ['minMax' => $this->_minMaxData, 'categories' => $this->_categories]);
    }

    public function transform($data) {
        $data = Dev::apply('encoding.feature.transform_input', $data);
        $transformedData = [];
        foreach ($data as $row) {
            $newRow = $this->_structures->vec();
            foreach ($row as $i => $value) {
                if (Arr::has($this->_numericalFeaturesIndices, $i, true)) {
                    $minMax = $this->_minMaxData->get($i);
                    if (is_array($minMax) && Arr::count($minMax) === 2) {
                        [$min, $max] = $minMax;
                        $range = Num::make($max)->minus($min)->val();
                        $newRow->add($range == 0 ? 0.0 : Num::make($value)->minus($min)->divide($range)->val());
                    } else {
                        $newRow->add($value);
                    }
                } elseif (Arr::has($this->_categoricalFeaturesIndices, $i, true)) {
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
