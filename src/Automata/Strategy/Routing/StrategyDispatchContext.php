<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Automata\Support\RecordSnapshot;

/** Detached invocation identity: no adapter, run object, clock or shared input. */
final class StrategyDispatchContext
{
    private readonly array $record;
    public function __construct(array $record) { $this->record = RecordSnapshot::copy($record); }
    public function toArray(): array { return RecordSnapshot::copy($this->record); }
}
