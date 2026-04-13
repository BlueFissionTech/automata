<?php
namespace BlueFission\Automata\Genetic;

use BlueFission\Arr;
use BlueFission\Automata\Support\Evaluates;
use BlueFission\DevElation as Dev;
use BlueFission\Func;
use BlueFission\Num;

class Population {
    use Evaluates;

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
    public function initialize(int $size, Func|callable $initializer): void {
        $current = $this->_individuals->val();
        $initializer = $this->asFunc($initializer);

        for ($i = 0; $i < $size; $i = Num::increment($i)) {
            $current[] = $this->invokeFunc($initializer, [$i, $current, $this]);
        }

        $this->_individuals->val($current);
        Dev::do('automata.genetic.population.initialize.action1', ['initialized' => $this->_individuals->val()]);
    }

    public function mutate(Func|callable $mutator): void {
        $mutator = $this->asFunc($mutator);

        foreach ($this->_individuals as $individual) {
            $this->invokeFunc($mutator, [$individual, $this]);
        }
        Dev::do('automata.genetic.population.mutate.action1', ['mutated' => $this->_individuals->val()]);
    }

    public function selection(Func|callable $selector): self {
        $selector = $this->asFunc($selector);
        $newIndividuals = $this->invokeFunc($selector, [$this->_individuals->val(), $this]);
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

