<?php

namespace BlueFission\Automata\Encoding;

use BlueFission\Vec;
use BlueFission\DevElation as Dev;

class CategoricalEncoder {
    private $_oneHot = false;
    private $_mapping = [];
    private $_defaultCategory = null; // Used for unseen categories

    public function __construct($oneHot = false, $defaultCategory = null) {
        $this->_oneHot = Dev::apply('encoding.categorical.onehot', $oneHot);
        $this->_defaultCategory = Dev::apply('encoding.categorical.default', $defaultCategory);
        Dev::do('encoding.categorical.construct', [
            'oneHot' => $this->_oneHot,
            'defaultCategory' => $this->_defaultCategory,
        ]);
    }

    public function fit($data) {
        $data = Dev::apply('encoding.categorical.fit_input', $data);
        $unique = array_unique($data);
        $this->_mapping = array_flip($unique);
        if ($this->_defaultCategory !== null) {
            $this->_mapping[$this->_defaultCategory] = count($this->_mapping);
        }
        Dev::do('encoding.categorical.fit', ['mapping' => $this->_mapping]);
    }

    public function transform($data) {
        $data = Dev::apply('encoding.categorical.transform_input', $data);
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

        $result = array_map(function($item) {
            return $this->_mapping[$item] ?? $this->_mapping[$this->_defaultCategory] ?? null;
        }, $data);
        $result = Dev::apply('encoding.categorical.transform_output', $result);
        Dev::do('encoding.categorical.transformed', ['result' => $result]);
        return $result;
    }
}
