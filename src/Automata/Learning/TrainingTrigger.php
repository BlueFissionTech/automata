<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;

/** Host-admitted signals are normalized observations, never permission to train. */
final class TrainingTrigger
{
    public const SIGNALS = ['novelty', 'drift', 'failure', 'latency', 'cost', 'training_cost'];
    private readonly array $record;

    public function __construct(array $signals = [], array $correctedOutcomes = [], bool $operatorRequested = false)
    {
        $signals = RecordSnapshot::copy($signals);
        foreach ($signals as $name => $value) {
            if (!Arr::has(self::SIGNALS, $name, true)) { throw new InvalidArgumentException('Unknown training pressure signal.'); }
            $signals[$name] = RecordSnapshot::number($value, $name, 0, 1);
        }
        $references = [];
        foreach (RecordSnapshot::copy($correctedOutcomes) as $reference) {
            if (!Arr::is($reference) || Arr::count($reference) !== 2) { throw new InvalidArgumentException('Correction requires exact outcome lineage.'); }
            $reference = ['experience_id' => RecordSnapshot::identifier($reference['experience_id'] ?? null, 'experience id'),
                'outcome_id' => RecordSnapshot::identifier($reference['outcome_id'] ?? null, 'outcome id')];
            $key = RecordSnapshot::fingerprint($reference);
            if (isset($references[$key])) { throw new InvalidArgumentException('Duplicate correction reference.'); }
            $references[$key] = $reference;
        }
        $this->record = ['signals' => $signals, 'corrected_outcomes' => Arr::make($references)->values()->val(),
            'operator_requested' => $operatorRequested];
    }

    public function toArray(): array { return $this->record; }
}
