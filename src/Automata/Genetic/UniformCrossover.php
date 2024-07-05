<?php
namespace BlueFission\Automata\Genetic;

class UniformCrossover extends Crossover {
    private $_crossoverRate;

    public function __construct(float $crossoverRate = 0.5) {
        $this->_crossoverRate = $crossoverRate;
    }

    public function cross($parent1, $parent2) {
        $offspring = clone $parent1;

        foreach ($parent1->toArray() as $key => $value) {
            if (mt_rand() / mt_getrandmax() < $this->_crossoverRate) {
                $offspring->field($key, $parent2->field($key));
            }
        }

        return $offspring;
    }
}