<?php

namespace BlueFission\Automata\LLM\Generation;

use BlueFission\Arr;

class GenerationStep extends GenerationValue
{
    public function id(): string
    {
        return (string)$this->field('step_id');
    }

    public function status(): string
    {
        return (string)$this->field('status');
    }

    public function diagnostics(): array
    {
        return Arr::make($this->field('diagnostics') ?? [])
            ->map(static fn (mixed $diagnostic): GenerationDiagnostic => $diagnostic instanceof GenerationDiagnostic
                ? $diagnostic
                : new GenerationDiagnostic(Arr::make($diagnostic)->toArray()))
            ->toArray();
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['diagnostics'] = Arr::make($this->diagnostics())
            ->map(static fn (GenerationDiagnostic $diagnostic): array => $diagnostic->toArray())
            ->toArray();

        return $data;
    }

    protected function defaults(): array
    {
        return [
            'step_id' => '',
            'name' => '',
            'kind' => 'generation',
            'status' => GenerationStatus::QUEUED,
            'input_ref' => null,
            'output_artifact_ids' => [],
            'usage' => [],
            'evidence' => [],
            'diagnostics' => [],
            'started_at' => null,
            'completed_at' => null,
            'metadata' => [],
        ];
    }
}
