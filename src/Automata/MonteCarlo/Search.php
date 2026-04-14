<?php

namespace BlueFission\Automata\MonteCarlo;

use BlueFission\Arr;
use BlueFission\Behavioral\Configurable;
use BlueFission\Behavioral\IConfigurable;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Collections\Collection;
use BlueFission\Func;
use BlueFission\Automata\Support\Evaluates;

class Search implements IConfigurable, IDispatcher
{
    use Configurable {
        Configurable::__construct as private __configConstruct;
    }
    use Evaluates;

    public const EVENT_SEARCH_STARTED = 'automata.montecarlo.search.started';
    public const EVENT_ROLLOUT_COMPLETED = 'automata.montecarlo.search.rollout_completed';
    public const EVENT_SEARCH_COMPLETED = 'automata.montecarlo.search.completed';

    protected array $_config = [
        'iterations' => 100,
        'seed' => null,
    ];

    public function __construct(int $iterations = 100, ?int $seed = null)
    {
        if ($iterations < 1) {
            throw new \InvalidArgumentException('Iterations must be at least 1.');
        }

        $this->__configConstruct([
            'iterations' => $iterations,
            'seed' => $seed,
        ]);
    }

    public function evaluate(array $actions, Func|callable $rollout): SearchResult
    {
        if (Arr::size($actions) === 0) {
            throw new \InvalidArgumentException('At least one action is required.');
        }

        $random = new RandomSource($this->seed());
        $statistics = [];

        $this->dispatch(self::EVENT_SEARCH_STARTED, [
            'actions' => $actions,
            'iterations' => $this->iterations(),
        ]);

        foreach ($actions as $action) {
            $statistics[$this->actionKey($action)] = new ActionStatistics($action);
        }

        $actionCount = Arr::size($actions);

        for ($iteration = 0; $iteration < $this->iterations(); $iteration++) {
            $action = $actions[$iteration % $actionCount];
            $key = $this->actionKey($action);
            $visit = $statistics[$key]->getVisits() + 1;
            $reward = (float)$this->numericValue(
                $this->invokeFunc($rollout, [$action, $random, $iteration, $visit, $this]),
                0.0
            );

            $statistics[$key]->record($reward);

            $this->dispatch(self::EVENT_ROLLOUT_COMPLETED, [
                'iteration' => $iteration,
                'action' => $action,
                'visit' => $visit,
                'reward' => $reward,
            ]);
        }

        $ranked = (new Collection(array_values($statistics)))->sort(function (ActionStatistics $left, ActionStatistics $right): int {
            $meanOrder = $right->getMeanReward() <=> $left->getMeanReward();
            if ($meanOrder !== 0) {
                return $meanOrder;
            }

            $visitOrder = $right->getVisits() <=> $left->getVisits();
            if ($visitOrder !== 0) {
                return $visitOrder;
            }

            return $right->getBestReward() <=> $left->getBestReward();
        })->toArray();

        $result = new SearchResult($ranked);

        $this->dispatch(self::EVENT_SEARCH_COMPLETED, [
            'best_action' => $result->getBestAction(),
            'statistics' => $result->toArray(),
        ]);

        return $result;
    }

    public function iterations(): int
    {
        return (int)$this->config('iterations');
    }

    public function seed(): ?int
    {
        $seed = $this->config('seed');

        return $seed === null ? null : (int)$seed;
    }

    private function actionKey($action): string
    {
        if (is_scalar($action) || $action === null) {
            return (string)$action;
        }

        return md5(serialize($action));
    }
}
