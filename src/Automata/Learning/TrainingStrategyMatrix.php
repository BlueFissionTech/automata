<?php

namespace BlueFission\Automata\Learning;

/** Selects the strongest available strategy; the trait still enforces thresholds. */
final class TrainingStrategyMatrix implements TrainingStrategy
{
    /** @param non-empty-list<TrainingStrategy> $strategies */
    public function __construct(private readonly array $strategies)
    {
        if ($strategies === []) {
            throw new \InvalidArgumentException('A strategy matrix needs at least one strategy.');
        }
        foreach ($strategies as $strategy) {
            if (!$strategy instanceof TrainingStrategy) {
                throw new \InvalidArgumentException('Every matrix entry must be a TrainingStrategy.');
            }
        }
    }

    public function train(array $observations): void
    {
        foreach ($this->strategies as $strategy) {
            $strategy->train($observations);
        }
    }

    public function predict(array $input, array $context): TrainingPrediction
    {
        $best = TrainingPrediction::unavailable();
        foreach ($this->strategies as $strategy) {
            $candidate = $strategy->predict($input, $context);
            if ($candidate->available && (!$best->available || $candidate->confidence > $best->confidence)) {
                $best = $candidate;
            }
        }
        return $best;
    }
}
