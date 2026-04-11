<?php

namespace BlueFission\Automata\MonteCarlo;

use BlueFission\Behavioral\Configurable;
use BlueFission\Behavioral\IConfigurable;
use BlueFission\Behavioral\IDispatcher;

class TreeSearch implements IConfigurable, IDispatcher
{
    use Configurable {
        Configurable::__construct as private __configConstruct;
    }

    public const EVENT_SEARCH_STARTED = 'automata.montecarlo.treesearch.started';
    public const EVENT_NODE_EXPANDED = 'automata.montecarlo.treesearch.node_expanded';
    public const EVENT_ITERATION_COMPLETED = 'automata.montecarlo.treesearch.iteration_completed';
    public const EVENT_SEARCH_COMPLETED = 'automata.montecarlo.treesearch.completed';

    protected array $_config = [
        'iterations' => 100,
        'exploration_constant' => 1.41421356237,
        'rollout_depth' => 10,
        'seed' => null,
    ];

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

        $this->__configConstruct([
            'iterations' => $iterations,
            'exploration_constant' => $explorationConstant,
            'rollout_depth' => $rolloutDepth,
            'seed' => $seed,
        ]);
    }

    public function search(
        $initialState,
        callable $legalActions,
        callable $transition,
        callable $isTerminal,
        callable $reward
    ): TreeSearchResult {
        $random = new RandomSource($this->seed());
        $root = new TreeSearchNode($initialState, null, null, array_values($legalActions($initialState)));

        $this->dispatch(self::EVENT_SEARCH_STARTED, [
            'initial_state' => $initialState,
            'iterations' => $this->iterations(),
            'exploration_constant' => $this->explorationConstant(),
            'rollout_depth' => $this->rolloutDepth(),
        ]);

        for ($iteration = 0; $iteration < $this->iterations(); $iteration++) {
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

                $this->dispatch(self::EVENT_NODE_EXPANDED, [
                    'iteration' => $iteration,
                    'action' => $action,
                    'state' => $state,
                ]);
            }

            $simulationState = $state;
            $depth = 0;
            while (!$isTerminal($simulationState) && $depth < $this->rolloutDepth()) {
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

            $this->dispatch(self::EVENT_ITERATION_COMPLETED, [
                'iteration' => $iteration,
                'score' => $score,
                'simulation_state' => $simulationState,
            ]);
        }

        $result = new TreeSearchResult($root);

        $this->dispatch(self::EVENT_SEARCH_COMPLETED, $result->toArray());

        return $result;
    }

    public function iterations(): int
    {
        return (int)$this->config('iterations');
    }

    public function explorationConstant(): float
    {
        return (float)$this->config('exploration_constant');
    }

    public function rolloutDepth(): int
    {
        return (int)$this->config('rollout_depth');
    }

    public function seed(): ?int
    {
        $seed = $this->config('seed');

        return $seed === null ? null : (int)$seed;
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
                + $this->explorationConstant() * sqrt(log($parentVisits) / $child->getVisits());

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
