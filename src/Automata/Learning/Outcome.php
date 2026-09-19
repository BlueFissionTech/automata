<?php

namespace BlueFission\Automata\Learning;

use InvalidArgumentException;
use JsonSerializable;

/** An observed consequence. Success describes observation, never authorization. */
final class Outcome implements JsonSerializable
{
    private readonly array $record;

    public function __construct(
        string $id,
        string $experienceId,
        string $source,
        bool $successful,
        array $observations = [],
        array $attribution = []
    ) {
        $this->record = RecordSnapshot::copy([
            'id' => RecordSnapshot::identifier($id, 'outcome id'),
            'experience_id' => RecordSnapshot::identifier($experienceId, 'experience id'),
            'source' => RecordSnapshot::identifier($source, 'outcome source'),
            'successful' => $successful,
            'observations' => $observations,
            'attribution' => $attribution,
        ]);
    }

    public static function fromArray(array $record): self
    {
        foreach (['id', 'experience_id', 'source'] as $field) {
            RecordSnapshot::identifier($record[$field] ?? null, $field);
        }
        if (!is_bool($record['successful'] ?? null)
            || !is_array($record['observations'] ?? null)
            || !is_array($record['attribution'] ?? null)) {
            throw new InvalidArgumentException('Malformed outcome record.');
        }
        return new self($record['id'], $record['experience_id'], $record['source'],
            $record['successful'], $record['observations'], $record['attribution']);
    }

    public function id(): string { return $this->record['id']; }
    public function experienceId(): string { return $this->record['experience_id']; }
    public function successful(): bool { return $this->record['successful']; }
    public function observations(): array { return $this->record['observations']; }
    public function toArray(): array { return $this->record; }
    public function jsonSerialize(): array { return $this->toArray(); }
}
