<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Automata\Strategy\IStrategy;

/** A caller-owned trained model and its declared training provenance. */
final class ModelCandidate
{
    private readonly string $id;
    private readonly string $version;

    public function __construct(
        string $id,
        string $version,
        private readonly IStrategy $strategy,
        private readonly TrainingBatch $training
    ) {
        $this->id = RecordSnapshot::identifier($id, 'strategy id');
        $this->version = RecordSnapshot::identifier($version, 'strategy version');
    }

    public function identity(): array { return ['id' => $this->id, 'version' => $this->version]; }
    public function strategy(): IStrategy { return $this->strategy; }
    public function training(): TrainingBatch { return $this->training; }
}
