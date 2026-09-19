<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Automata\Context;
use BlueFission\Automata\Language\Statement;
use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use InvalidArgumentException;
use JsonSerializable;

/** Immutable, versioned snapshot; application-owned input normalization precedes capture. */
final class Experience implements JsonSerializable
{
    public const SCHEMA_VERSION = 1;
    private readonly array $record;

    public function __construct(array $record)
    {
        $record = RecordSnapshot::copy($record);
        if (($record['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported experience schema version.');
        }
        RecordSnapshot::identifier($record['id'] ?? null, 'experience id');
        RecordSnapshot::identifier($record['timestamp'] ?? null, 'timestamp');
        foreach (['statements', 'context', 'outcomes', 'provenance', 'metadata'] as $field) {
            if (!Arr::is($record[$field] ?? null)) {
                throw new InvalidArgumentException('Malformed experience field: ' . $field);
            }
        }
        foreach (['data', 'tags', 'normalizations'] as $field) {
            if (!Arr::is($record['context'][$field] ?? null)) {
                throw new InvalidArgumentException('Malformed context field: ' . $field);
            }
        }
        if (!Arr::check($record['statements'], 'array_is_list') || !Arr::check($record['outcomes'], 'array_is_list')) {
            throw new InvalidArgumentException('Statements and outcomes must be lists.');
        }
        foreach ($record['statements'] as $statement) {
            if (!Arr::is($statement)) {
                throw new InvalidArgumentException('Each statement must be a semantic snapshot.');
            }
        }
        $seen = [];
        foreach ($record['outcomes'] as $outcomeRecord) {
            if (!Arr::is($outcomeRecord)) {
                throw new InvalidArgumentException('Each outcome must be a record.');
            }
            $outcome = Outcome::fromArray($outcomeRecord);
            if ($outcome->experienceId() !== $record['id'] || isset($seen[$outcome->id()])) {
                throw new InvalidArgumentException('Outcome lineage is inconsistent or duplicated.');
            }
            $seen[$outcome->id()] = true;
        }
        $this->record = $record;
    }

    /** @param Statement[] $statements */
    public static function fromStatements(string $id, array $statements, Context $context, array $options = []): self
    {
        $snapshots = [];
        foreach ($statements as $statement) {
            if (!$statement instanceof Statement) {
                throw new InvalidArgumentException('Expected normalized Statement instances.');
            }
            $snapshots[] = $statement->snapshot();
        }
        $experience = new self([
            'schema_version' => self::SCHEMA_VERSION,
            'id' => $id,
            'timestamp' => $options['timestamp'] ?? gmdate('c'),
            'statements' => $snapshots,
            'context' => ['data' => $context->all(), 'tags' => $context->tags(),
                'normalizations' => $context->normalizations()],
            'trace_id' => $options['trace_id'] ?? '',
            'provenance' => $options['provenance'] ?? [],
            'metadata' => $options['metadata'] ?? [],
            'outcomes' => [],
        ]);
        Dev::do('automata.experience.normalized', ['experience' => $experience]);
        return $experience;
    }

    public function withOutcome(Outcome $outcome): self
    {
        if ($outcome->experienceId() !== $this->id()) {
            throw new InvalidArgumentException('Outcome belongs to another experience.');
        }
        foreach ($this->outcomes() as $existing) {
            if ($existing->id() === $outcome->id()) {
                if ($existing->toArray() !== $outcome->toArray()) {
                    throw new InvalidArgumentException('Outcome id already has different evidence.');
                }
                return $this;
            }
        }
        $record = $this->record;
        $record['outcomes'][] = $outcome->toArray();
        $experience = new self($record);
        Dev::do('automata.experience.outcome.recorded', ['experience' => $experience, 'outcome' => $outcome]);
        return $experience;
    }

    public function id(): string { return $this->record['id']; }
    /** @return Outcome[] */
    public function outcomes(): array { return Arr::map($this->record['outcomes'], Outcome::fromArray(...)); }
    public function toArray(): array { return $this->record; }
    public function jsonSerialize(): array { return $this->toArray(); }
}
