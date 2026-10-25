<?php

namespace BlueFission\Automata\Strategy\Workflow;

use BlueFission\Automata\Support\RecordSnapshot;

/**
 * Detached internal emission container; later evidence is a separate record.
 * This snapshots serializable data; it does not validate a portable schema or authority.
 * External construction alone cannot establish a valid execution receipt.
 */
final class WorkflowAdmissionReceipt
{
    private readonly array $record;
    public function __construct(array $record) { $this->record = RecordSnapshot::copy($record); }
    public function toArray(): array { return RecordSnapshot::copy($this->record); }
}
