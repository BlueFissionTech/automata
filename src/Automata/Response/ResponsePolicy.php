<?php

namespace BlueFission\Automata\Response;

use BlueFission\Arr;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;

final class ResponsePolicy
{
    private readonly array $record;

    public function __construct(array $record = [])
    {
        $record = RecordSnapshot::copy($record);
        if (Arr::count(array_diff(array_keys($record), ['threshold', 'minimum_threshold'])) > 0) {
            throw new InvalidArgumentException('Unknown response policy field.');
        }
        $record = ['threshold' => 1.0, 'minimum_threshold' => 1.0, ...$record];
        $record = ['threshold' => RecordSnapshot::number($record['threshold'], 'threshold', 0.0, 1.0),
            'minimum_threshold' => RecordSnapshot::number($record['minimum_threshold'], 'policy floor', 0.0, 1.0)];
        if ($record['threshold'] < $record['minimum_threshold']) {
            throw new InvalidArgumentException('Response threshold cannot fall below its policy floor.');
        }
        $this->record = $record;
    }

    public function threshold(): float { return $this->record['threshold']; }
    public function toArray(): array { return $this->record; }
}
