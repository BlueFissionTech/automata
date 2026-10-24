<?php

namespace BlueFission\Automata\Strategy\Workflow;

use BlueFission\Automata\Support\RecordSnapshot;

/** Detached append-only observation; later evidence must be a separate record. */
final class WorkflowAdmissionReceipt
{
    private readonly array $record;
    public function __construct(array $record) { $this->record = RecordSnapshot::copy($record); }
    public function toArray(): array { return RecordSnapshot::copy($this->record); }
}
