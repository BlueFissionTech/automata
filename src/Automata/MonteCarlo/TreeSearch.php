<?php

namespace BlueFission\Automata\MonteCarlo;

class TreeSearch
{
    private int $iterations;
    private float $explorationConstant;
    private int $rolloutDepth;
    private ?int $seed;

    public function __construct(
        int $iterations = 100,
        float $explorationConstant = 1.41421356237,
        int $rolloutDepth = 10,
        ?int $seed = null
    ) {
        if ($iterations < 1) {
            throw new \InvalidArgumentException('Iterations must be at least 1.');
        }

        if ($rolloutDepth < 0) {
            throw new \InvalidArgumentException('Rollout depth must be zero or greater.');
        }

        $this->iterations = $iterations;
        $this->explorationConstant = $explorationConstant;
        $this->rolloutDepth = $rolloutDepth;
        $this->seed = $seed;
    }

    public function search(
        $initialState,
        callable $legalActions,
        callable $transition,
        callable $isTerminal,
        callable $reward
    ): TreeSearchResult {
        $random = new RandomSource($this->seed);
        $root = new TreeSearchNode($initialState, null, null, array_values($legalActions($initialState)));

        for ($iteration = 0; $iteration < $this->iterations; $iteration++) {
            $node = $root;
            $state = $initialState;

            while (
                !$isTerminal($state)
                && !$node->hasUntriedActions()
                && !empty($node->getChildren())
            ) {
                $node = $this->selectChild($node);
                $state = $node->getState();
            }

            if (!$isTerminal($state) && $node->hasUntriedActions()) {
                $action = $node->takeUntriedAction($random);
                $state = $transition($state, $action, $random);
                $child = new TreeSearchNode($state, $node, $action, array_values($legalActions($state)));
                $node->addChild($child);
                $node = $child;
            }

            $simulationState = $state;
            $depth = 0;
            while (!$isTerminal($simulationState) && $depth < $this->rolloutDepth) {
                $actions = array_values($legalActions($simulationState));
                if (empty($actions)) {
                    break;
                }

                $simulationAction = $random->pick($actions);
                $simulationState = $transition($simulationState, $simulationAction, $random);
                $depth++;
            }

            $score = (float)$reward($simulationState);

            while ($node !== null) {
                $node->record($score);
                $node = $node->getParent();
            }
        }

        return new TreeSearchResult($root);
    }

    private function selectChild(TreeSearchNode $node): TreeSearchNode
    {
        $bestChild = null;
        $bestScore = -INF;
        $parentVisits = max(1, $node->getVisits());

        foreach ($node->getChildren() as $child) {
            if ($child->getVisits() === 0) {
                return $child;
            }

            $score = $child->getMeanReward()
                + $this->explorationConstant * sqrt(log($parentVisits) / $child->getVisits());

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestChild = $child;
            }
        }

        if ($bestChild === null) {
            throw new \RuntimeException('Unable to select child from empty node.');
        }

        return $bestChild;
    }
}
