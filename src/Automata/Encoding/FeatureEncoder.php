<?php

namespace BlueFission\Automata\Encoding;

use BlueFission\Vec;

class FeatureEncoder {
    private $numericalFeaturesIndices;
    private $categoricalFeaturesIndices;
    private $minMaxData;
    private $categories;

    public function __construct($numericalFeaturesIndices, $categoricalFeaturesIndices) {
        $this->numericalFeaturesIndices = $numericalFeaturesIndices;
        $this->categoricalFeaturesIndices = $categoricalFeaturesIndices;
        $this->minMaxData = new Vec();
        $this->categories = new Vec();
    }

    public function fit($data) {
        foreach ($this->numericalFeaturesIndices as $index) {
            $column = array_column($data, $index);
            $min = min($column);
            $max = max($column);
            $this->minMaxData->add([$min, $max]);
        }

        foreach ($this->categoricalFeaturesIndices as $index) {
            $column = array_column($data, $index);
            $this->categories->add(array_unique($column));
        }
    }

    public function transform($data) {
        $transformedData = [];
        foreach ($data as $row) {
            $newRow = new Vec();
            foreach ($row as $i => $value) {
                if (in_array($i, $this->numericalFeaturesIndices)) {
                    list($min, $max) = $this->minMaxData->get($i);
                    $newRow->add(($value - $min) / ($max - $min));
                } elseif (in_array($i, $this->categoricalFeaturesIndices)) {
                    foreach ($this->categories->get($i) as $category) {
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
