<?php

namespace BlueFission\Automata\Genetic;

use BlueFission\DevElation as Dev;

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

        $fields = Dev::apply('automata.genetic.randommutation.mutate.1', $individual->toArray());

        foreach ($fields as $key => $value) {
            $roll = mt_rand() / mt_getrandmax();
            if ($roll < $this->_mutationRate && is_numeric($value)) {
                $delta = (mt_rand() / mt_getrandmax() * 2.0) - 1.0;
                $individual->field($key, $value + $delta);
            }
        }

        Dev::do('automata.genetic.randommutation.mutate.action1', ['individual' => $individual]);
    }
}
