<?php
namespace BlueFission\Bot\Genetic;

abstract class Crossover {
    public abstract function cross($parent1, $parent2);
}
