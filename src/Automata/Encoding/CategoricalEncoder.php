<?php

namespace BlueFission\Automata\Encoding;

use BlueFission\Vec;

class CategoricalEncoder {
    private $_oneHot = false;
    private $_mapping = [];
    private $_defaultCategory = null; // Used for unseen categories

    public function __construct($oneHot = false, $defaultCategory = null) {
        $this->_oneHot = $oneHot;
        $this->_defaultCategory = $defaultCategory;
    }

    public function fit($data) {
        $unique = array_unique($data);
        $this->_mapping = array_flip($unique);
        if ($this->_defaultCategory !== null) {
            $this->_mapping[$this->_defaultCategory] = count($this->_mapping); // Add default category
        }
    }

    public function transform($data) {
        if ($this->_oneHot) {
            return array_map(function($item) {
                $vector = new Vec(array_fill(0, count($this->_mapping), 0));
                $index = $this->_mapping[$item] ?? $this->_mapping[$this->_defaultCategory] ?? null;
                if ($index !== null) {
                    $vector->set($index, 1);
                }
                return $vector;
            }, $data);
        }

        return array_map(function($item) {
            return $this->_mapping[$item] ?? $this->_mapping[$this->_defaultCategory] ?? null;
        }, $data);
    }
}
