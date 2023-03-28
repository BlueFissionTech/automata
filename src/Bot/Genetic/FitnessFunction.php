<?php
namespace BlueFission\Bot\Genetic;

abstract class FitnessFunction {
    public abstract function evaluate($individual): float;
}

