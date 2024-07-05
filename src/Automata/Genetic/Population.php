<?php
namespace BlueFission\Automata\Genetic;

use BlueFission\Arr;

class Population {
    private $_individuals;

    public function __construct(array $individuals = []) {
        $this->_individuals = new Arr($individuals);
    }

    public function initialize(int $size, callable $initializer): void {
        for ($i = 0; $i < $size; $i++) {
            $this->_individuals[] = $initializer();
        }
    }

    public function mutate(callable $mutator): void {
        foreach ($this->_individuals as $individual) {
            $mutator($individual);
        }
    }

    public function selection(callable $selector): self {
        $newIndividuals = $selector($this->_individuals->val());
        return new self($newIndividuals);
    }

    public function getIndividuals(): array {
        return $this->_individuals->val();
    }
}

