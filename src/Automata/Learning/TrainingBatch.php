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
        $records = Arr::make($examples)
            ->map(static function ($example): array {
                if (!$example instanceof TrainingExample) {
                    throw new InvalidArgumentException('Expected TrainingExample instances.');
                }
                return $example->toArray();
            })
            ->values()
            ->val();
        $this->record = [
            'projection_id' => RecordSnapshot::identifier($projectionId, 'projection id'),
            'projection_version' => RecordSnapshot::identifier($projectionVersion, 'projection version'),
            'examples' => $records,
        ];
    }

    public function samples(): array
    {
        return Arr::make($this->record['examples'])
            ->map(static fn (array $record) => $record['sample'])
            ->val();
    }
    public function labels(): array
    {
        return Arr::make($this->record['examples'])
            ->map(static fn (array $record) => $record['label'])
            ->val();
    }
    public function toArray(): array { return $this->record; }
    public function jsonSerialize(): array { return $this->toArray(); }
}
