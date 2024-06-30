<?php
namespace BlueFission\Automata\Genetic;

class Population {
    private $individuals = [];

    public function __construct(array $individuals = []) {
        $this->individuals = $individuals;
    }

    public function initialize(int $size, callable $initializer): void {
        for ($i = 0; $i < $size; $i++) {
            $this->individuals[] = $initializer();
        }
    }

    public function mutate(callable $mutator): void {
        foreach ($this->individuals as $individual) {
            $mutator($individual);
        }
    }

    public function selection(callable $selector): self {
        $newIndividuals = $selector($this->individuals);
        return new self($newIndividuals);
    }

    public function getIndividuals(): array {
        return $this->individuals;
    }
}

