<?php
namespace BlueFission\Automata\Genetic;

use BlueFission\Arr;

class Population {
    private $_individuals;

    public function __construct(array $individuals = []) {
        $this->_individuals = new Arr($individuals);
    }

    /**
     * Initialize the population with a fixed number of individuals.
     *
     * Uses Arr::val() to avoid relying on offsetSet([]) semantics,
     * which can be problematic with certain internal configurations.
     */
    public function initialize(int $size, callable $initializer): void {
        $current = $this->_individuals->val();

        for ($i = 0; $i < $size; $i++) {
            $current[] = $initializer();
        }

        $this->_individuals->val($current);
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

