<?php
namespace BlueFission\Automata\Genetic;

abstract class FitnessFunction {
    public abstract function evaluate($individual): float;
}

