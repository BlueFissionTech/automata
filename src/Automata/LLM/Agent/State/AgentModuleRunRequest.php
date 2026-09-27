<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;

final class AgentModuleRunRequest
{
    private GovernanceDecision $authorization;
    private array $data;

    public function __construct(GovernanceDecision $authorization, array $data = [])
    {
        $runId = trim((string)($data['run_id'] ?? '')) ?: TaskTraceSpan::id('module_run');
        $this->authorization = $authorization;
        $this->data = [
            'contract_version' => AgentModuleLifecycle::VERSION,
            'run_id' => $runId,
            'task_id' => trim((string)($data['task_id'] ?? '')) ?: $runId,
            'trace_id' => trim((string)($data['trace_id'] ?? '')) ?: $runId,
            'correlation_id' => trim((string)($data['correlation_id'] ?? '')) ?: $runId,
            'causation_id' => $this->nullableString($data['causation_id'] ?? null),
            'context' => Arr::make($data['context'] ?? [])->toArray(),
            'limits' => Arr::make($data['limits'] ?? [])->toArray(),
            'requested_features' => Arr::make($data['requested_features'] ?? [])
                ->map(static fn (mixed $feature): string => trim((string)$feature))
                ->filter(static fn (string $feature): bool => $feature !== '')
                ->toArray(),
            'cancellation_requested' => (bool)($data['cancellation_requested'] ?? false),
            'metadata' => Arr::make($data['metadata'] ?? [])->toArray(),
        ];
    }

    public function authorization(): GovernanceDecision
    {
        return $this->authorization;
    }

    public function context(): array
    {
        return $this->data['context'];
    }

    public function limits(): array
    {
        return $this->data['limits'];
    }

    public function requestedFeatures(): array
    {
        return $this->data['requested_features'];
    }

    public function cancellationRequested(): bool
    {
        return $this->data['cancellation_requested'];
    }

    public function lineage(): array
    {
        return [
            'run_id' => $this->data['run_id'],
            'task_id' => $this->data['task_id'],
            'trace_id' => $this->data['trace_id'],
            'correlation_id' => $this->data['correlation_id'],
            'causation_id' => $this->data['causation_id'],
        ];
    }

    public function toArray(): array
    {
        $data = $this->data;
        $data['authorization'] = $this->authorization->toArray();

        return $data;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
}
