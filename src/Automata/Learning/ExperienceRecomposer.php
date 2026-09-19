<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Val;
use BlueFission\DevElation as Dev;
use InvalidArgumentException;

/** Projects recorded observations without training or replacing live strategies. */
final class ExperienceRecomposer
{
    /** @param iterable<Experience> $experiences */
    public function compose(iterable $experiences, ITrainingAdapter $adapter): TrainingBatch
    {
        $examples = [];
        $seen = [];
        foreach ($experiences as $experience) {
            if (!$experience instanceof Experience) {
                throw new InvalidArgumentException('Expected Experience instances.');
            }
            $outcomeIds = Arr::make($experience->outcomes())
                ->map(static fn (Outcome $outcome): string => $outcome->id())
                ->val();
            $projected = Dev::apply('automata.learning.examples', $adapter->project($experience));
            if (!Val::check($projected, 'is_iterable')) {
                throw new InvalidArgumentException('Training projections must be iterable.');
            }
            foreach ($projected as $example) {
                if (!$example instanceof TrainingExample) {
                    throw new InvalidArgumentException('Projection must return TrainingExample instances.');
                }
                $record = $example->toArray();
                if ($record['experience_id'] !== $experience->id()
                    || !Arr::has($outcomeIds, $record['outcome_id'], true)) {
                    throw new InvalidArgumentException('Training example must cite an outcome from its source experience.');
                }
                $key = json_encode([$record['experience_id'], $record['outcome_id']], JSON_THROW_ON_ERROR);
                if (isset($seen[$key])) {
                    if ($seen[$key] !== $record) {
                        throw new InvalidArgumentException('Conflicting projections for the same outcome.');
                    }
                    continue;
                }
                $seen[$key] = $record;
                $examples[] = $example;
            }
        }
        $batch = new TrainingBatch($adapter->id(), $adapter->version(), $examples);
        Dev::do('automata.learning.recomposed', ['batch' => $batch]);
        return $batch;
    }
}
