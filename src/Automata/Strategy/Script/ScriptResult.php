<?php

namespace BlueFission\Automata\Strategy\Script;

use BlueFission\Arr;
use BlueFission\Automata\Support\RecordSnapshot;

/** Detached execution evidence; restoring a report never restores execution authority. */
final class ScriptResult
{
    private array $data;

    /** @internal Execution owns construction of reports, including unknown measurements. */
    public function __construct(array $data) { $this->data = RecordSnapshot::copy($data); }

    /** Completed rendering and explicit early exit both provide usable output. */
    public function succeeded(): bool { return Arr::make(['completed', 'early_exit'])->has($this->status(), true); }

    /** Denied, cancelled and uncertain are distinct terminal states. */
    public function status(): string { return $this->data['status']; }

    /** Empty string and literal zero are valid successful output; failure returns null. */
    public function output(): ?string { return $this->data['output']; }

    /** Return detached scalar evidence, without parsers, closures or grants. */
    public function toArray(): array { return RecordSnapshot::copy($this->data); }
}
