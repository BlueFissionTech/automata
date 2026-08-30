<?php

namespace BlueFission\Automata\LLM\Generation;

use BlueFission\Arr;

class GenerationRunResult extends GenerationValue
{
    public function status(): string
    {
        return (string)$this->field('status');
    }

    public function isTerminal(): bool
    {
        return GenerationStatus::isTerminal($this->status());
    }

    public function isSuccessful(): bool
    {
        return $this->status() === GenerationStatus::COMPLETED;
    }

    public function steps(): array
    {
        return Arr::make($this->field('steps') ?? [])
            ->map(static fn (mixed $step): GenerationStep => $step instanceof GenerationStep
                ? $step
                : new GenerationStep(Arr::make($step)->toArray()))
            ->toArray();
    }

    public function artifacts(): array
    {
        return Arr::make($this->field('artifacts') ?? [])
            ->map(static fn (mixed $artifact): GeneratedArtifact => $artifact instanceof GeneratedArtifact
                ? $artifact
                : new GeneratedArtifact(Arr::make($artifact)->toArray()))
            ->toArray();
    }

    public function diagnostics(): array
    {
        return Arr::make($this->field('diagnostics') ?? [])
            ->map(static fn (mixed $diagnostic): GenerationDiagnostic => $diagnostic instanceof GenerationDiagnostic
                ? $diagnostic
                : new GenerationDiagnostic(Arr::make($diagnostic)->toArray()))
            ->toArray();
    }

    public static function completed(
        GenerationRunRequest $request,
        array $artifacts = [],
        array $steps = [],
        array $data = []
    ): self {
        return self::fromRequest($request, GenerationStatus::COMPLETED, $steps, $artifacts, [], $data);
    }

    public static function partial(
        GenerationRunRequest $request,
        array $artifacts,
        array $diagnostics,
        array $steps = [],
        array $data = []
    ): self {
        return self::fromRequest($request, GenerationStatus::PARTIAL, $steps, $artifacts, $diagnostics, $data);
    }

    public static function failed(
        GenerationRunRequest $request,
        array $diagnostics,
        array $steps = [],
        array $data = []
    ): self {
        return self::fromRequest($request, GenerationStatus::FAILED, $steps, [], $diagnostics, $data);
    }

    public static function cancelled(GenerationRunRequest $request, array $data = []): self
    {
        return self::fromRequest($request, GenerationStatus::CANCELLED, [], [], [], $data);
    }

    public static function timedOut(GenerationRunRequest $request, array $data = []): self
    {
        return self::fromRequest($request, GenerationStatus::TIMED_OUT, [], [], [], $data);
    }

    public static function fromRequest(
        GenerationRunRequest $request,
        string $status,
        array $steps = [],
        array $artifacts = [],
        array $diagnostics = [],
        array $data = []
    ): self {
        $requestData = $request->toArray();

        return new self(Arr::merge($data, [
            'contract_version' => $requestData['contract_version'] ?? '1.0',
            'run_id' => $request->runId(),
            'task_id' => $request->taskId(),
            'correlation_id' => $requestData['correlation_id'] ?? '',
            'causation_id' => $requestData['causation_id'] ?? '',
            'trace_id' => $requestData['trace_id'] ?? '',
            'status' => $status,
            'steps' => $steps,
            'artifacts' => $artifacts,
            'diagnostics' => $diagnostics,
            'policy' => $requestData['policy'] ?? [],
            'evidence' => $requestData['evidence'] ?? [],
        ]));
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['steps'] = Arr::make($this->steps())
            ->map(static fn (GenerationStep $step): array => $step->toArray())
            ->toArray();
        $data['artifacts'] = Arr::make($this->artifacts())
            ->map(static fn (GeneratedArtifact $artifact): array => $artifact->toArray())
            ->toArray();
        $data['diagnostics'] = Arr::make($this->diagnostics())
            ->map(static fn (GenerationDiagnostic $diagnostic): array => $diagnostic->toArray())
            ->toArray();

        return $data;
    }

    protected function defaults(): array
    {
        return [
            'contract_version' => '1.0',
            'run_id' => '',
            'task_id' => '',
            'correlation_id' => '',
            'causation_id' => '',
            'trace_id' => '',
            'status' => GenerationStatus::QUEUED,
            'steps' => [],
            'artifacts' => [],
            'diagnostics' => [],
            'usage' => [],
            'policy' => [],
            'evidence' => [],
            'continuation' => null,
            'started_at' => null,
            'completed_at' => null,
            'metadata' => [],
        ];
    }
}
