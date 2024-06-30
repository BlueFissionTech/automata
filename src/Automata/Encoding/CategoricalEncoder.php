<?php

namespace BlueFission\Automata\Encoding;

use BlueFission\Vec;

class CategoricalEncoder {
    private $oneHot = false;
    private $mapping = [];
    private $defaultCategory = null; // Used for unseen categories

    public function __construct($oneHot = false, $defaultCategory = null) {
        $this->oneHot = $oneHot;
        $this->defaultCategory = $defaultCategory;
    }

    public function fit($data) {
        $unique = array_unique($data);
        $this->mapping = array_flip($unique);
        if ($this->defaultCategory !== null) {
            $this->mapping[$this->defaultCategory] = count($this->mapping); // Add default category
        }
    }

    public function transform($data) {
        if ($this->oneHot) {
            return array_map(function($item) {
                $vector = new Vec(array_fill(0, count($this->mapping), 0));
                $index = $this->mapping[$item] ?? $this->mapping[$this->defaultCategory] ?? null;
                if ($index !== null) {
                    $vector->set($index, 1);
                }
                return $vector;
            }, $data);
        }

        return array_map(function($item) {
            return $this->mapping[$item] ?? $this->mapping[$this->defaultCategory] ?? null;
        }, $data);
    }
}
