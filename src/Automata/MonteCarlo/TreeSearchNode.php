<?php

namespace BlueFission\Automata\MonteCarlo;

class TreeSearchNode
{
    private $state;
    private $action;
    private ?self $parent;
    private array $children = [];
    private array $untriedActions;
    private int $visits = 0;
    private float $totalReward = 0.0;

    public function __construct($state, ?self $parent = null, $action = null, array $untriedActions = [])
    {
        $this->state = $state;
        $this->parent = $parent;
        $this->action = $action;
        $this->untriedActions = array_values($untriedActions);
    }

    public function getState()
    {
        return $this->state;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @return self[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }

    public function hasUntriedActions(): bool
    {
        return !empty($this->untriedActions);
    }

    public function takeUntriedAction(RandomSource $random)
    {
        $index = $random->nextInt(0, count($this->untriedActions) - 1);
        $action = $this->untriedActions[$index];
        array_splice($this->untriedActions, $index, 1);

        return $action;
    }

    public function record(float $reward): void
    {
        $this->visits++;
        $this->totalReward += $reward;
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

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'state' => $this->state,
            'visits' => $this->visits,
            'total_reward' => $this->totalReward,
            'mean_reward' => $this->getMeanReward(),
            'children' => array_map(function (self $child): array {
                return $child->toArray();
            }, $this->children),
        ];
    }
}
