<?php

namespace BlueFission\Automata\MonteCarlo;

use BlueFission\Arr;
use BlueFission\Collections\Collection;

class SearchResult
{
    /**
     * @var ActionStatistics[]
     */
    private array $statistics;

    public function __construct(array $statistics)
    {
        $this->statistics = $statistics;
    }

    /**
     * @return ActionStatistics[]
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function getBestAction()
    {
        $best = $this->getBestStatistics();

        return $best ? $best->getAction() : null;
    }

    public function getBestStatistics(): ?ActionStatistics
    {
        if (Arr::size($this->statistics) === 0) {
            return null;
        }

        return $this->statistics[0];
    }

    public function toArray(): array
    {
        return (new Collection($this->statistics))->map(function (ActionStatistics $statistics): array {
            return $statistics->toArray();
        })->toArray();
    }
}
