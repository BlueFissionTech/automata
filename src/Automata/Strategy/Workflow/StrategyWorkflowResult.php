<?php

namespace BlueFission\Automata\Strategy\Workflow;

use BlueFission\Automata\Support\RecordSnapshot;
use JsonSerializable;

/** Detached observation of a run; possession of this record grants no authority. */
final class StrategyWorkflowResult implements JsonSerializable
{
    private array $record;
    public function __construct(array $record) { $this->record = RecordSnapshot::copy($record); }
    public function status(): string { return $this->record['status']; }
    public function outputs(): array { return $this->record['outputs']; }
    public function toArray(): array { return $this->record; }
    public function jsonSerialize(): array { return $this->toArray(); }
}
