<?php

namespace BlueFission\Automata\Genetic;

/**
 * Simple numeric mutation operator that nudges scalar fields by a small random delta.
 *
 * Intended for use with DevElation-style objects that expose `toArray()` and `field()`.
 */
class RandomMutation extends Mutation
{
    private float $_mutationRate;

    public function __construct(float $mutationRate = 0.1)
    {
        $this->_mutationRate = $mutationRate;
    }

    public function mutate($individual): void
    {
        if (!method_exists($individual, 'toArray') || !method_exists($individual, 'field')) {
            return;
        }

        foreach ($individual->toArray() as $key => $value) {
            $roll = mt_rand() / mt_getrandmax();
            if ($roll < $this->_mutationRate && is_numeric($value)) {
                $delta = (mt_rand() / mt_getrandmax() * 2.0) - 1.0;
                $individual->field($key, $value + $delta);
            }
        }
    }
}
