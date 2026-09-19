<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use InvalidArgumentException;
use JsonSerializable;

final class TrainingBatch implements JsonSerializable
{
    private readonly array $record;

    /** @param TrainingExample[] $examples */
    public function __construct(string $projectionId, string $projectionVersion, array $examples)
    {
        $records = [];
        foreach ($examples as $example) {
            if (!$example instanceof TrainingExample) {
                throw new InvalidArgumentException('Expected TrainingExample instances.');
            }
            $records[] = $example->toArray();
        }
        $this->record = [
            'projection_id' => RecordSnapshot::identifier($projectionId, 'projection id'),
            'projection_version' => RecordSnapshot::identifier($projectionVersion, 'projection version'),
            'examples' => $records,
        ];
    }

    public function samples(): array { return Arr::map($this->record['examples'], static fn (array $record) => $record['sample']); }
    public function labels(): array { return Arr::map($this->record['examples'], static fn (array $record) => $record['label']); }
    public function toArray(): array { return $this->record; }
    public function jsonSerialize(): array { return $this->toArray(); }
}
