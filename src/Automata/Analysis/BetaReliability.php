<?php

namespace BlueFission\Automata\Analysis;

use BlueFission\Obj;

/**
 * BetaReliability
 *
 * Maintains Beta(a,b) posteriors for success/failure processes
 * such as route success, edge passability, or asset reliability.
 *
 * Each tracked key starts from a Beta(1,1) prior by default.
 */
class BetaReliability extends Obj
{
    /** @var array<string,array{alpha:float,beta:float}> */
    protected array $priors = [];

    /**
     * Update reliability for a given key with a success/failure outcome.
     *
     * @param string $key
     * @param bool   $success
     * @param float  $weight  optional weight for the observation
     */
    public function update(string $key, bool $success, float $weight = 1.0): void
    {
        $params = $this->priors[$key] ?? ['alpha' => 1.0, 'beta' => 1.0];

        if ($success) {
            $params['alpha'] += $weight;
        } else {
            $params['beta'] += $weight;
        }

        $this->priors[$key] = $params;
    }

    /**
     * Get mean reliability for a key (E[p]).
     */
    public function mean(string $key): float
    {
        $params = $this->priors[$key] ?? ['alpha' => 1.0, 'beta' => 1.0];
        $a = $params['alpha'];
        $b = $params['beta'];

        return $a / ($a + $b);
    }

    /**
     * Get raw alpha/beta parameters for a key.
     *
     * @return array{alpha:float,beta:float}
     */
    public function parameters(string $key): array
    {
        $params = $this->priors[$key] ?? ['alpha' => 1.0, 'beta' => 1.0];
        return $params;
    }
}

