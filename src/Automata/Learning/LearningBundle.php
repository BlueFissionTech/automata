<?php

namespace BlueFission\Automata\Learning;

use BlueFission\{Arr, Str};
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;

/** Immutable manifest binding compatible workflow, policy and model artifacts. */
final class LearningBundle
{
    private readonly array $record;

    /**
     * Component kinds are extensible; the manifest contains no executable code.
     * Each named slot declares an artifact and exact dependencies on other slots.
     * Hosts still validate artifact format and semantic compatibility on loading.
     */
    public function __construct(string $id, string $version, string $scope, array $components)
    {
        RecordSnapshot::identifier($id, 'bundle id');
        RecordSnapshot::identifier($version, 'bundle version');
        RecordSnapshot::identifier($scope, 'bundle scope');
        $components = RecordSnapshot::copy($components);
        if ($components === [] || !Arr::check($components, 'array_is_list')) { throw new InvalidArgumentException('Bundle components must be a nonempty list.'); }
        $slots = []; $identities = [];
        foreach ($components as $component) {
            self::fields($component, ['slot', 'kind', 'id', 'version', 'artifact', 'requires']);
            foreach (['slot', 'kind', 'id', 'version'] as $field) { RecordSnapshot::identifier($component[$field], $field); }
            if (isset($slots[$component['slot']])) { throw new InvalidArgumentException('Bundle slots must be unique.'); }
            self::fields($component['artifact'], ['key', 'format', 'sha256']);
            RecordSnapshot::identifier($component['artifact']['key'], 'artifact key');
            RecordSnapshot::identifier($component['artifact']['format'], 'artifact format');
            self::digestValue($component['artifact']['sha256']);
            if (!Arr::is($component['requires']) || !Arr::check($component['requires'], 'array_is_list')) { throw new InvalidArgumentException('Component dependencies must be a list.'); }
            $identity = RecordSnapshot::fingerprint([$component['kind'], $component['id'], $component['version']]);
            $artifact = RecordSnapshot::fingerprint($component['artifact']);
            if (isset($identities[$identity]) && $identities[$identity] !== $artifact) { throw new InvalidArgumentException('One component identity cannot bind different artifacts.'); }
            $identities[$identity] = $artifact;
            $slots[$component['slot']] = $component;
        }
        foreach ($components as $component) {
            $seen = [];
            foreach ($component['requires'] as $dependency) {
                self::fields($dependency, ['slot', 'id', 'version', 'sha256']);
                foreach (['slot', 'id', 'version'] as $field) { RecordSnapshot::identifier($dependency[$field], $field); }
                self::digestValue($dependency['sha256']);
                $target = $slots[$dependency['slot']] ?? null;
                if ($target === null || isset($seen[$dependency['slot']]) || $dependency['slot'] === $component['slot']
                    || $target['id'] !== $dependency['id'] || $target['version'] !== $dependency['version']
                    || $target['artifact']['sha256'] !== $dependency['sha256']) {
                    throw new InvalidArgumentException('Missing, duplicate or incompatible bundle dependency.');
                }
                $seen[$dependency['slot']] = true;
            }
        }
        $this->record = ['schema_version' => 1, 'id' => $id, 'version' => $version, 'scope' => $scope, 'components' => $components];
    }

    /** Restore the declared schema without discarding unknown fields. */
    public static function fromArray(array $record): self
    {
        self::fields($record, ['schema_version', 'id', 'version', 'scope', 'components']);
        if ($record['schema_version'] !== 1 || !Arr::is($record['components'])) { throw new InvalidArgumentException('Unsupported bundle schema.'); }
        return new self(RecordSnapshot::identifier($record['id'], 'bundle id'), RecordSnapshot::identifier($record['version'], 'bundle version'),
            RecordSnapshot::identifier($record['scope'], 'bundle scope'), $record['components']);
    }

    /** Scope is an opaque host-owned compatibility and isolation boundary. */
    public function scope(): string { return $this->record['scope']; }
    /** Exact manifest identity remains distinct from its complete content digest. */
    public function identity(): array { return Arr::make($this->record)->filter(static fn ($value, $key) => Arr::has(['id', 'version', 'scope'], $key, true))->val(); }
    /** Detached manifest data is safe to retain with an activation receipt. */
    public function toArray(): array { return $this->record; }
    /** Type-preserving manifest digest binds all declared compatibility relationships. */
    public function digest(): string { return RecordSnapshot::fingerprint($this->record); }
    /** Opaque artifact keys are interpreted only by a trusted host resolver. */
    public function components(): array { return $this->record['components']; }

    /** Reject malformed records before accessing their required fields. */
    private static function fields(mixed $record, array $fields): void
    {
        if (!Arr::is($record) || Arr::size($record) !== Arr::size($fields)
            || Arr::make($record)->keys()->diff($fields)->val() !== []) { throw new InvalidArgumentException('Malformed bundle record fields.'); }
    }
    /** Artifact digests refer to exact bytes, including serialized fitted parameters. */
    private static function digestValue(mixed $digest): void
    {
        if (!Str::is($digest) || !Str::matches($digest, '/^[a-f0-9]{64}$/D')) { throw new InvalidArgumentException('Artifact SHA-256 must be lowercase hexadecimal.'); }
    }
}
