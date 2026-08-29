<?php

namespace BlueFission\Automata\Language\Claims;

use BlueFission\Arr;
use BlueFission\Automata\Language\Statement;

class ClaimNormalizationResult extends ClaimValue
{
    public function status(): string
    {
        return (string)$this->field('status');
    }

    public function normalized(): bool
    {
        return $this->status() === ClaimNormalizationStatus::NORMALIZED;
    }

    public function envelope(): ClaimEnvelope
    {
        $envelope = $this->field('envelope');

        return $envelope instanceof ClaimEnvelope
            ? $envelope
            : new ClaimEnvelope(Arr::make($envelope)->toArray());
    }

    public function statement(): ?Statement
    {
        $statement = $this->field('statement');

        return $statement instanceof Statement ? $statement : null;
    }

    public function predicate(): ?ClaimPredicate
    {
        $predicate = $this->field('predicate');
        if ($predicate === null || $predicate === []) {
            return null;
        }

        return $predicate instanceof ClaimPredicate
            ? $predicate
            : new ClaimPredicate(Arr::make($predicate)->toArray());
    }

    public function diagnostics(): array
    {
        return array_map(
            static fn (mixed $diagnostic): ClaimDiagnostic => $diagnostic instanceof ClaimDiagnostic
                ? $diagnostic
                : new ClaimDiagnostic(Arr::make($diagnostic)->toArray()),
            Arr::make($this->field('diagnostics') ?? [])->toArray()
        );
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['envelope'] = $this->envelope()->toArray();
        $data['statement'] = $this->statement()?->snapshot();
        $data['predicate'] = $this->predicate()?->toArray();
        $data['diagnostics'] = array_map(
            static fn (ClaimDiagnostic $diagnostic): array => $diagnostic->toArray(),
            $this->diagnostics()
        );

        return $data;
    }

    protected function defaults(): array
    {
        return [
            'status' => ClaimNormalizationStatus::MALFORMED,
            'envelope' => [],
            'statement' => null,
            'predicate' => null,
            'evidence' => [],
            'diagnostics' => [],
            'metadata' => [],
        ];
    }
}
