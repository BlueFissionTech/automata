<?php
namespace BlueFission\Automata\Genetic;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;

class Population {
    private $_individuals;

    public function __construct(array $individuals = []) {
        $individuals = Dev::apply('automata.genetic.population.__construct.1', $individuals);
        $this->_individuals = new Arr($individuals);
        Dev::do('automata.genetic.population.__construct.action1', ['individuals' => $this->_individuals->val()]);
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
        Dev::do('automata.genetic.population.initialize.action1', ['initialized' => $this->_individuals->val()]);
    }

    public function mutate(callable $mutator): void {
        foreach ($this->_individuals as $individual) {
            $mutator($individual);
        }
        Dev::do('automata.genetic.population.mutate.action1', ['mutated' => $this->_individuals->val()]);
    }

    public function selection(callable $selector): self {
        $newIndividuals = $selector($this->_individuals->val());
        $newIndividuals = Dev::apply('automata.genetic.population.selection.1', $newIndividuals);
        Dev::do('automata.genetic.population.selection.action1', ['selected' => $newIndividuals]);
        return new self($newIndividuals);
    }

    public function getIndividuals(): array {
        $individuals = $this->_individuals->val();
        $individuals = Dev::apply('automata.genetic.population.getIndividuals.1', $individuals);
        Dev::do('automata.genetic.population.getIndividuals.action1', ['individuals' => $individuals]);
        return $individuals;
    }
}

