<?php

namespace BlueFission\Automata\Markov;

use BlueFission\Obj;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\Behaviors\Event;

/**
 * DiscreteMarkov
 *
 * Generic helper for discrete-time Markov processes over a
 * finite state space. Suitable for infrastructure, demand,
 * or any other categorical system where state transitions
 * are governed by a fixed transition matrix.
 *
 * Example state sets:
 * - roads:   {open, degraded, closed}
 * - towers:  {up, congested, down}
 * - demand:  {stable, rising, critical}
 *
 * The class is intentionally stateless with respect to the
 * matrix and distribution; callers supply those for each
 * step, which makes it easy to:
 * - swap matrices based on external conditions (e.g., weather),
 * - reuse the helper for multiple systems, and
 * - keep behavior deterministic for testing.
 */
class DiscreteMarkov extends Obj
{
    use Dispatches;

    /**
     * Compute next-state distribution given current distribution and transition matrix.
     *
     * @param array<string,float>               $current state => probability
     * @param array<string,array<string,float>> $matrix  fromState => [toState => probability]
     * @return array<string,float>                       next state distribution
     */
    public function step(array $current, array $matrix): array
    {
        $next = [];

        foreach ($current as $state => $prob) {
            if ($prob <= 0.0 || !isset($matrix[$state])) {
                continue;
            }

            foreach ($matrix[$state] as $toState => $tProb) {
                $next[$toState] = ($next[$toState] ?? 0.0) + $prob * $tProb;
            }
        }

        $sum = array_sum($next);
        if ($sum > 0) {
            foreach ($next as $state => $value) {
                $next[$state] = $value / $sum;
            }
        }

        $this->dispatch(new Event('markov.discrete.step', [
            'current' => $current,
            'next' => $next,
        ]));

        return $next;
    }

    /**
     * Perform multiple steps and return the final distribution.
     *
     * @param array<string,float>               $current
     * @param array<string,array<string,float>> $matrix
     * @param int                               $steps
     * @return array<string,float>
     */
    public function stepMany(array $current, array $matrix, int $steps): array
    {
        $distribution = $current;
        for ($i = 0; $i < $steps; $i++) {
            $distribution = $this->step($distribution, $matrix);
        }

        return $distribution;
    }
}
