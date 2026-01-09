<?php
namespace BlueFission\Automata\GameTheory;

use BlueFission\Arr;

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
        $key = $this->keyFor($actions);
        $table = $this->_matrix->val();
        $table[$key] = $payoffs;
        $this->_matrix->val($table);
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

        return $table[$key] ?? null;
    }

    /**
     * Export the underlying matrix for inspection or serialization.
     */
    public function toArray(): array
    {
        return $this->_matrix->val();
    }

    private function keyFor(array $actions): string
    {
        return implode('|', $actions);
    }
}

