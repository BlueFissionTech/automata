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
        return array_map(
            static fn (mixed $diagnostic): GenerationDiagnostic => $diagnostic instanceof GenerationDiagnostic
                ? $diagnostic
                : new GenerationDiagnostic(Arr::make($diagnostic)->toArray()),
            Arr::make($this->field('diagnostics') ?? [])->toArray()
        );
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['diagnostics'] = array_map(
            static fn (GenerationDiagnostic $diagnostic): array => $diagnostic->toArray(),
            $this->diagnostics()
        );

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
