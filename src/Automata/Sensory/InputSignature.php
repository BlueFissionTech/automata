<?php

namespace BlueFission\Automata\Sensory;

use BlueFission\Arr;
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable, versioned feature projection used to scope reusable route learning.
 *
 * Hosts select features after each processing pass. Raw payloads, timestamps,
 * traces and session ids should remain in evidence unless they actually define
 * a routing class. This value imposes no modality catalog, classifier, topology
 * or feature vocabulary, and does not grant authority or infer confidence.
 */
final class InputSignature implements JsonSerializable
{
    private readonly array $record;

    /** Detach finite data before assigning a semantic projection identity. */
    public function __construct(string $projectionId, string $projectionVersion, array $features)
    {
        $this->record = ['schema_version' => 1, 'projection' => [
            'id' => RecordSnapshot::identifier($projectionId, 'projection id'),
            'version' => RecordSnapshot::identifier($projectionVersion, 'projection version'),
        ], 'features' => RecordSnapshot::copy($features)];
    }

    /** Map order is immaterial; list order and scalar types retain their meaning. */
    public function key(): string
    {
        return 'input:' . RecordSnapshot::fingerprint($this->record);
    }

    /** Return detached features; altering them cannot change an existing signature. */
    public function features(): array { return $this->record['features']; }

    /** Replace the complete projection for another pass, including removed facts. */
    public function withFeatures(array $features): self
    {
        return new self($this->record['projection']['id'], $this->record['projection']['version'], $features);
    }

    /** Persist only data; projection code and permission remain host responsibilities. */
    public function toArray(): array { return $this->record; }

    /** Import the declared schema without silently dropping unexpected fields. */
    public static function fromArray(array $record): self
    {
        $record = RecordSnapshot::copy($record);
        if (($record['schema_version'] ?? null) !== 1 || !Arr::is($record['projection'] ?? null)
            || !Arr::is($record['features'] ?? null)
            || Arr::make($record)->keys()->diff(['schema_version', 'projection', 'features'])->val() !== []
            || Arr::make($record['projection'])->keys()->diff(['id', 'version'])->val() !== []) {
            throw new InvalidArgumentException('Malformed input signature.');
        }
        return new self(RecordSnapshot::identifier($record['projection']['id'] ?? null, 'projection id'),
            RecordSnapshot::identifier($record['projection']['version'] ?? null, 'projection version'), $record['features']);
    }

    /** JSON encoders must preserve scalar/float types when retaining identity. */
    public function jsonSerialize(): array { return $this->toArray(); }
}
