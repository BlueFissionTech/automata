<?php

namespace BlueFission\Automata\MonteCarlo;

class ActionStatistics
{
    private $action;
    private int $visits = 0;
    private float $totalReward = 0.0;
    private float $bestReward = -INF;

    public function __construct($action)
    {
        $this->action = $action;
    }

    public function record(float $reward): void
    {
        $this->visits++;
        $this->totalReward += $reward;
        $this->bestReward = max($this->bestReward, $reward);
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getVisits(): int
    {
        return $this->visits;
    }

    public function getTotalReward(): float
    {
        return $this->totalReward;
    }

    public function getMeanReward(): float
    {
        if ($this->visits === 0) {
            return 0.0;
        }

        return $this->totalReward / $this->visits;
    }

    public function getBestReward(): float
    {
        if ($this->visits === 0) {
            return 0.0;
        }

        return $this->bestReward;
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'visits' => $this->getVisits(),
            'total_reward' => $this->getTotalReward(),
            'mean_reward' => $this->getMeanReward(),
            'best_reward' => $this->getBestReward(),
        ];
    }
}
