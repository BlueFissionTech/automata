<?php

namespace BlueFission\Automata\MonteCarlo;

use BlueFission\Arr;
use BlueFission\Behavioral\Configurable;
use BlueFission\Behavioral\IConfigurable;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Func;
use BlueFission\Num;
use BlueFission\Automata\Support\Evaluates;

class TreeSearch implements IConfigurable, IDispatcher
{
    use Configurable {
        Configurable::__construct as private __configConstruct;
    }
    use Evaluates;

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
        Func|callable $legalActions,
        Func|callable $transition,
        Func|callable $isTerminal,
        Func|callable $reward
    ): TreeSearchResult {
        $random = new RandomSource($this->seed());
        $root = new TreeSearchNode(
            $initialState,
            null,
            null,
            array_values(Arr::toArray($this->invokeFunc($legalActions, [$initialState, null, $this])))
        );

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
                !(bool)$this->invokeFunc($isTerminal, [$state, $node, $this])
                && !$node->hasUntriedActions()
                && Arr::size($node->getChildren()) > 0
            ) {
                $node = $this->selectChild($node);
                $state = $node->getState();
            }

            if (!(bool)$this->invokeFunc($isTerminal, [$state, $node, $this]) && $node->hasUntriedActions()) {
                $action = $node->takeUntriedAction($random);
                $state = $this->invokeFunc($transition, [$state, $action, $random, $node, $this]);
                $child = new TreeSearchNode(
                    $state,
                    $node,
                    $action,
                    array_values(Arr::toArray($this->invokeFunc($legalActions, [$state, $node, $this])))
                );
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
            while (!(bool)$this->invokeFunc($isTerminal, [$simulationState, $node, $this]) && $depth < $this->rolloutDepth()) {
                $actions = array_values(Arr::toArray($this->invokeFunc($legalActions, [$simulationState, $node, $this])));
                if (Arr::size($actions) === 0) {
                    break;
                }

                $simulationAction = $random->pick($actions);
                $simulationState = $this->invokeFunc(
                    $transition,
                    [$simulationState, $simulationAction, $random, $node, $this]
                );
                $depth++;
            }

            $score = (float)$this->numericValue(
                $this->invokeFunc($reward, [$simulationState, $node, $this]),
                0.0
            );

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
        $parentVisits = (int)Num::max(1, $node->getVisits());

        foreach ($node->getChildren() as $child) {
            if ($child->getVisits() === 0) {
                return $child;
            }

            $exploration = (float)Num::multiply(
                $this->explorationConstant(),
                Num::sqrt(
                    Num::divide(
                        Num::log($parentVisits),
                        $child->getVisits()
                    )
                )
            );
            $score = (float)Num::add($child->getMeanReward(), $exploration);

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
