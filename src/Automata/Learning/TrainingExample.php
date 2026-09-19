<?php

namespace BlueFission\Automata\Learning;

use JsonSerializable;

final class TrainingExample implements JsonSerializable
{
    private readonly array $record;

    public function __construct(string $experienceId, string $outcomeId, mixed $sample, mixed $label)
    {
        $this->record = RecordSnapshot::copy([
            'experience_id' => RecordSnapshot::identifier($experienceId, 'experience id'),
            'outcome_id' => RecordSnapshot::identifier($outcomeId, 'outcome id'),
            'sample' => $sample,
            'label' => $label,
        ]);
    }

    public function toArray(): array { return $this->record; }
    public function jsonSerialize(): array { return $this->toArray(); }
}
