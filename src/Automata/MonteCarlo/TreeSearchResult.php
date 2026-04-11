<?php

namespace BlueFission\Automata\MonteCarlo;

class TreeSearchResult
{
    private TreeSearchNode $root;

    public function __construct(TreeSearchNode $root)
    {
        $this->root = $root;
    }

    public function getRoot(): TreeSearchNode
    {
        return $this->root;
    }

    public function getBestAction()
    {
        $children = $this->root->getChildren();

        if (empty($children)) {
            return null;
        }

        usort($children, function (TreeSearchNode $left, TreeSearchNode $right): int {
            $visitOrder = $right->getVisits() <=> $left->getVisits();
            if ($visitOrder !== 0) {
                return $visitOrder;
            }

            return $right->getMeanReward() <=> $left->getMeanReward();
        });

        return $children[0]->getAction();
    }

    public function toArray(): array
    {
        return [
            'best_action' => $this->getBestAction(),
            'root' => $this->root->toArray(),
        ];
    }
}
