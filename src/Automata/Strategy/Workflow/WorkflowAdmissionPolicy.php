<?php

namespace BlueFission\Automata\Strategy\Workflow;

use BlueFission\Num;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;

/** Exportable configuration only. Runtime clocks/deadlines belong to a run. */
final class WorkflowAdmissionPolicy
{
    public readonly int $maximumInFlight;
    public function __construct(mixed $maximumInFlight, public readonly string $version = '1')
    {
        if (!Num::isInt($maximumInFlight) || $maximumInFlight < 1) {
            throw new InvalidArgumentException('maximumInFlight must be a positive integer.');
        }
        RecordSnapshot::identifier($version, 'admission policy version');
        $this->maximumInFlight = $maximumInFlight;
    }
    public function toArray(): array { return ['schema_version' => 1, 'version' => $this->version, 'maximum_in_flight' => $this->maximumInFlight]; }
}
