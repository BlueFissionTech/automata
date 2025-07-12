<?php

namespace BlueFission\Automata\Memory;

class TemporalEdge
{
    public float $initialWeight;
    public float $decayRate;
    public int $timestamp;

    public function __construct(float $weight = 1.0, float $decayRate = 0.001)
    {
        $this->initialWeight = $weight;
        $this->decayRate = $decayRate;
        $this->timestamp = time();
    }

    public function weightNow(): float
    {
        $age = time() - $this->timestamp;
        return $this->initialWeight * exp(-$this->decayRate * $age);
    }

    public function reinforce(float $amount = 1.0): void
    {
        $this->initialWeight += $amount;
        $this->timestamp = time(); // refresh recency
    }
}
