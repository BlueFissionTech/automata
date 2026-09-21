<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\DevElation as Dev;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;

/** Evidence pressure requests training; hard sample bounds and host authority remain separate. */
final class TrainingPolicy
{
    private readonly float $minimumPressure;
    private readonly array $weights;

    public function __construct(
        private readonly int $minimumExamples = 10,
        private readonly int $minimumNewExamples = 10,
        private readonly int $minimumCorrections = 1,
        mixed $minimumPressure = 1.0,
        private readonly int $maximumExamples = 1000,
        array $weights = []
    ) {
        if ($minimumExamples < 1 || $minimumNewExamples < 1 || $minimumCorrections < 1 || $maximumExamples < $minimumExamples) {
            throw new InvalidArgumentException('Training sample bounds must be positive and ordered.');
        }
        $this->minimumPressure = RecordSnapshot::number($minimumPressure, 'minimum pressure');
        if ($this->minimumPressure <= 0) { throw new InvalidArgumentException('Minimum pressure must be positive.'); }
        $normalized = Arr::make(TrainingTrigger::SIGNALS)->flip()->map(static fn (): float => 1.0)->val();
        foreach (RecordSnapshot::copy($weights) as $name => $value) {
            if (!Arr::has(TrainingTrigger::SIGNALS, $name, true)) { throw new InvalidArgumentException('Unknown pressure weight.'); }
            $normalized[$name] = RecordSnapshot::number($value, 'pressure weight', 0, 1000);
        }
        $this->weights = $normalized;
    }

    public function assess(TrainingBatch $batch, TrainingBatch $learned, TrainingTrigger $trigger): array
    {
        $current = $batch->toArray();
        $previous = $learned->toArray();
        if ($current['projection_id'] !== $previous['projection_id'] || $current['projection_version'] !== $previous['projection_version']) {
            throw new InvalidArgumentException('Training projection must match the learned evidence.');
        }
        if (Arr::count($current['examples']) > $this->maximumExamples) { throw new InvalidArgumentException('Training batch exceeds its sample bound.'); }
        $known = $this->indexed($previous['examples']);
        $rows = $this->indexed($current['examples']);
        $new = [];
        foreach ($rows as $key => $row) {
            if (isset($known[$key])) {
                if (RecordSnapshot::fingerprint($known[$key]) !== RecordSnapshot::fingerprint($row)) {
                    throw new InvalidArgumentException('Previously learned outcome lineage cannot be rewritten.');
                }
            } else { $new[$key] = $row; }
        }
        $signals = $trigger->toArray();
        foreach ($signals['corrected_outcomes'] as $reference) {
            if (!isset($new[RecordSnapshot::fingerprint($reference)])) { throw new InvalidArgumentException('Correction must cite new evidence in this batch.'); }
        }
        $newCount = Arr::count($new);
        $correctedCount = Arr::count($signals['corrected_outcomes']);
        $pressure = Num::make($newCount)->divide($this->minimumNewExamples)
            ->add(Num::make($correctedCount)->divide($this->minimumCorrections)->val())->val();
        foreach ($signals['signals'] as $name => $value) {
            $weighted = Num::make($value)->multiply($this->weights[$name])->val();
            $pressure = Num::make($pressure)->add($name === 'training_cost' ? -$weighted : $weighted)->val();
        }
        $nonnegativePressure = Num::make(0.0)->max($pressure);
        $pressure = RecordSnapshot::number(Dev::apply('automata.learning.pressure', $nonnegativePressure), 'filtered training pressure');
        $enough = Arr::count($rows) >= $this->minimumExamples;
        $eligible = $enough && ($signals['operator_requested'] || $pressure >= $this->minimumPressure);
        return ['eligible' => $eligible, 'reason' => !$enough ? 'insufficient_examples' : ($eligible ? null : 'insufficient_pressure'),
            'examples' => Arr::count($rows), 'new_examples' => $newCount, 'corrected_examples' => $correctedCount,
            'pressure' => $pressure, 'minimum_pressure' => $this->minimumPressure,
            'minimum_examples' => $this->minimumExamples, 'maximum_examples' => $this->maximumExamples,
            'minimum_new_examples' => $this->minimumNewExamples, 'minimum_corrections' => $this->minimumCorrections,
            'weights' => $this->weights, 'trigger' => $signals];
    }

    private function indexed(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $key = RecordSnapshot::fingerprint(['experience_id' => $row['experience_id'], 'outcome_id' => $row['outcome_id']]);
            if (isset($indexed[$key])) { throw new InvalidArgumentException('Repeated training lineage cannot inflate pressure.'); }
            $indexed[$key] = $row;
        }
        return $indexed;
    }
}
