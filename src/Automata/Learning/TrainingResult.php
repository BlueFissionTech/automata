<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use JsonSerializable;

/** Detached training evidence plus an optional caller-owned model; never activation authority. */
final class TrainingResult implements JsonSerializable
{
    private readonly array $record;

    public function __construct(array $record, private readonly ?ModelCandidate $candidate = null)
    {
        $record = RecordSnapshot::copy($record);
        if (!Arr::has(['deferred', 'denied', 'trained', 'uncertain'], $record['status'] ?? null, true)
            || (($record['status'] === 'trained') !== ($candidate !== null))
            || ($candidate !== null && ($record['candidate'] ?? null) !== $candidate->identity())) {
            throw new InvalidArgumentException('Training result status and candidate must agree.');
        }
        $this->record = $record;
    }

    public function status(): string { return $this->record['status']; }
    public function candidate(): ?ModelCandidate { return $this->candidate; }
    public function toArray(): array { return $this->record; }
    public function jsonSerialize(): array { return $this->toArray(); }
}
