<?php
namespace BlueFission\Automata\GameTheory;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;

/**
 * PayoffMatrix
 *
 * Lightweight helper for normal-form games.
 * Stores payoffs for joint actions in a Develation Arr-backed table.
 *
 * The library remains generic; disaster-response–specific interpretations
 * live in examples.
 */
class PayoffMatrix
{
    /**
     * @var Arr<string,array> internal mapping from serialized joint-action keys to payoff vectors
     */
    private Arr $_matrix;

    public function __construct(array $matrix = [])
    {
        $this->_matrix = new Arr($matrix);
    }

    /**
     * Set the payoff vector for a joint action profile.
     *
     * @param string[] $actions  ordered list of actions, one per player
     * @param array    $payoffs  ordered list of payoffs, one per player
     */
    public function setPayoff(array $actions, array $payoffs): void
    {
        $actions = Dev::apply('automata.gametheory.payoffmatrix.setPayoff.1', $actions);
        $payoffs = Dev::apply('automata.gametheory.payoffmatrix.setPayoff.2', $payoffs);

        $key = $this->keyFor($actions);
        $table = $this->_matrix->val();
        $table[$key] = $payoffs;
        $this->_matrix->val($table);

        Dev::do('automata.gametheory.payoffmatrix.setPayoff.action1', ['actions' => $actions, 'payoffs' => $payoffs]);
    }

    /**
     * Retrieve the payoff vector for a joint action profile, if present.
     *
     * @param string[] $actions
     * @return array|null
     */
    public function getPayoff(array $actions): ?array
    {
        $key = $this->keyFor($actions);
        $table = $this->_matrix->val();

        $payoffs = $table[$key] ?? null;
        $payoffs = Dev::apply('automata.gametheory.payoffmatrix.getPayoff.1', $payoffs);
        Dev::do('automata.gametheory.payoffmatrix.getPayoff.action1', ['actions' => $actions, 'payoffs' => $payoffs]);

        return $payoffs;
    }

    /**
     * Export the underlying matrix for inspection or serialization.
     */
    public function toArray(): array
    {
        $matrix = $this->_matrix->val();
        $matrix = Dev::apply('automata.gametheory.payoffmatrix.toArray.1', $matrix);
        Dev::do('automata.gametheory.payoffmatrix.toArray.action1', ['matrix' => $matrix]);

        return $matrix;
    }

    private function keyFor(array $actions): string
    {
        return implode('|', $actions);
    }
}

