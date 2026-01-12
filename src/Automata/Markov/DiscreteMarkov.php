<?php

namespace BlueFission\Automata\Markov;

use BlueFission\Obj;
use BlueFission\DevElation as Dev;
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
        $current = Dev::apply('automata.markov.discretemarkov.step.1', $current);
        $matrix  = Dev::apply('automata.markov.discretemarkov.step.2', $matrix);

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

        $next = Dev::apply('automata.markov.discretemarkov.step.3', $next);
        Dev::do('automata.markov.discretemarkov.step.action1', ['current' => $current, 'next' => $next]);

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
        $current = Dev::apply('automata.markov.discretemarkov.stepMany.1', $current);
        $matrix  = Dev::apply('automata.markov.discretemarkov.stepMany.2', $matrix);
        Dev::do('automata.markov.discretemarkov.stepMany.action1', ['current' => $current, 'matrix' => $matrix, 'steps' => $steps]);

        $distribution = $current;
        for ($i = 0; $i < $steps; $i++) {
            $distribution = $this->step($distribution, $matrix);
        }

        $distribution = Dev::apply('automata.markov.discretemarkov.stepMany.3', $distribution);
        Dev::do('automata.markov.discretemarkov.stepMany.action2', ['final' => $distribution]);

        return $distribution;
    }
}
