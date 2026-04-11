<?php

namespace BlueFission\Automata\MonteCarlo;

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
        if (empty($this->statistics)) {
            return null;
        }

        return $this->statistics[0];
    }

    public function toArray(): array
    {
        return array_map(function (ActionStatistics $statistics): array {
            return $statistics->toArray();
        }, $this->statistics);
    }
}
