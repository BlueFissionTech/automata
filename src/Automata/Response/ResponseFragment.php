<?php

namespace BlueFission\Automata\Response;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Num;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;

/** Immutable production contract. Dependencies require successful primary receiver receipts. */
final class ResponseFragment
{
    private readonly array $record;

    public function __construct(array $record)
    {
        $record = RecordSnapshot::copy($record, 6);
        $defaults = ['id' => null, 'channel' => null, 'weight' => 1.0, 'priority' => 0,
            'blocking' => false, 'dependencies' => [], 'deadline_ms' => null, 'fallback' => null, 'trace' => []];
        foreach ($record as $key => $value) {
            if (!Arr::hasKey($defaults, $key)) { throw new InvalidArgumentException('Unknown fragment field: ' . $key); }
        }
        $record = [...$defaults, ...$record];
        RecordSnapshot::identifier($record['id'], 'fragment id');
        RecordSnapshot::identifier($record['channel'], 'channel');
        $record['weight'] = RecordSnapshot::number($record['weight'], 'weight');
        if (!Num::isInt($record['priority']) || !Flag::isBool($record['blocking'])
            || !Arr::is($record['trace']) || !Arr::is($record['dependencies'])
            || !Arr::check($record['dependencies'], 'array_is_list')) {
            throw new InvalidArgumentException('Malformed fragment priority, blocking flag, trace or dependencies.');
        }
        if ($record['deadline_ms'] !== null && (!Num::isInt($record['deadline_ms']) || $record['deadline_ms'] < 0)) {
            throw new InvalidArgumentException('Production deadline must be a nonnegative integer millisecond value.');
        }
        $seen = [];
        foreach ($record['dependencies'] as $dependency) {
            RecordSnapshot::identifier($dependency, 'dependency');
            if (isset($seen[$dependency])) { throw new InvalidArgumentException('Duplicate response dependency.'); }
            $seen[$dependency] = true;
        }
        if ($record['fallback'] !== null) {
            $fallback = $record['fallback'];
            if ($record['blocking'] || !Arr::is($fallback) || !Arr::hasKey($fallback, 'payload')
                || Arr::make($fallback)->keys()->diff(['payload', 'confidence'])->count() > 0) {
                throw new InvalidArgumentException('Only nonblocking fragments may declare fallback payload/confidence.');
            }
            $confidence = $fallback['confidence'] ?? null;
            $record['fallback'] = ['payload' => RecordSnapshot::canonical($fallback['payload']), 'confidence' => $confidence === null ? null
                : RecordSnapshot::number($confidence, 'fallback confidence', 0.0, 1.0)];
        }
        $record['trace'] = RecordSnapshot::canonical($record['trace']);
        $this->record = $record;
    }

    public function id(): string { return $this->record['id']; }
    public function toArray(): array { return $this->record; }
}
