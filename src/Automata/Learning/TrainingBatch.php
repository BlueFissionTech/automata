<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Automata\Support\RecordSnapshot as Snapshot;
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

    /** Restore finite labelled evidence, rejecting silent schema loss. */
    public static function fromArray(array $record): self
    {
        $record = RecordSnapshot::copy($record);
        if (Arr::make($record)->keys()->diff(['projection_id', 'projection_version', 'examples'])->val() !== []
            || !Arr::is($record['examples'] ?? null) || !Arr::check($record['examples'], 'array_is_list')) {
            throw new InvalidArgumentException('Malformed training batch.');
        }
        $examples = Arr::make($record['examples'])->map(static function ($row): TrainingExample {
            if (!Arr::is($row) || Arr::size($row) !== 4
                || Arr::make($row)->keys()->diff(['experience_id', 'outcome_id', 'sample', 'label'])->val() !== []) {
                throw new InvalidArgumentException('Malformed training example.');
            }
            return new TrainingExample(RecordSnapshot::identifier($row['experience_id'] ?? null, 'experience id'),
                RecordSnapshot::identifier($row['outcome_id'] ?? null, 'outcome id'), $row['sample'], $row['label']);
        })->val();
        return new self(RecordSnapshot::identifier($record['projection_id'] ?? null, 'projection id'),
            RecordSnapshot::identifier($record['projection_version'] ?? null, 'projection version'), $examples);
    }

    /**
     * Rehearse retained evidence with newly admitted labels without changing either
     * batch. Repeated identical lineage is idempotent; conflicting labels/samples
     * require a new reviewed outcome identity, never an in-place history rewrite.
     */
    public function merge(self $next): self
    {
        $other = $next->toArray();
        if ($this->record['projection_id'] !== $other['projection_id']
            || $this->record['projection_version'] !== $other['projection_version']) {
            throw new InvalidArgumentException('Cannot merge different training projections.');
        }
        $rows = [];
        foreach ([$this->record['examples'], $other['examples']] as $source) {
            foreach ($source as $row) {
                $key = Snapshot::fingerprint([$row['experience_id'], $row['outcome_id']]);
                if (isset($rows[$key]) && Snapshot::fingerprint($rows[$key]) !== Snapshot::fingerprint($row)) {
                    throw new InvalidArgumentException('Training lineage cannot be rewritten.');
                }
                $rows[$key] = $row;
            }
        }
        return self::fromArray([...$this->record, 'examples' => Arr::make($rows)->values()->val()]);
    }
}
