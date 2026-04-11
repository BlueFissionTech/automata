<?php

namespace BlueFission\Automata\MonteCarlo;

class Search
{
    private int $iterations;
    private ?int $seed;

    public function __construct(int $iterations = 100, ?int $seed = null)
    {
        if ($iterations < 1) {
            throw new \InvalidArgumentException('Iterations must be at least 1.');
        }

        $this->iterations = $iterations;
        $this->seed = $seed;
    }

    public function evaluate(array $actions, callable $rollout): SearchResult
    {
        if (empty($actions)) {
            throw new \InvalidArgumentException('At least one action is required.');
        }

        $random = new RandomSource($this->seed);
        $statistics = [];

        foreach ($actions as $action) {
            $statistics[$this->actionKey($action)] = new ActionStatistics($action);
        }

        for ($iteration = 0; $iteration < $this->iterations; $iteration++) {
            $action = $actions[$iteration % count($actions)];
            $key = $this->actionKey($action);
            $visit = $statistics[$key]->getVisits() + 1;
            $reward = (float)$rollout($action, $random, $iteration, $visit);

            $statistics[$key]->record($reward);
        }

        $ranked = array_values($statistics);
        usort($ranked, function (ActionStatistics $left, ActionStatistics $right): int {
            $meanOrder = $right->getMeanReward() <=> $left->getMeanReward();
            if ($meanOrder !== 0) {
                return $meanOrder;
            }

            $visitOrder = $right->getVisits() <=> $left->getVisits();
            if ($visitOrder !== 0) {
                return $visitOrder;
            }

            return $right->getBestReward() <=> $left->getBestReward();
        });

        return new SearchResult($ranked);
    }

    private function actionKey($action): string
    {
        if (is_scalar($action) || $action === null) {
            return (string)$action;
        }

        return md5(serialize($action));
    }
}
