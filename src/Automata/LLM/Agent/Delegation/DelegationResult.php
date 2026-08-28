<?php

namespace BlueFission\Automata\LLM\Agent\Delegation;

class DelegationResult extends DelegationValue
{
    public function status(): string
    {
        return (string)$this->field('status');
    }

    public function output(): mixed
    {
        return $this->field('output');
    }

    public static function rejected(DelegationRequest $request, string $agentId, string $reason): self
    {
        return self::fromRequest($request, $agentId, DelegationStatus::REJECTED, null, [
            'reason' => $reason,
        ]);
    }

    public static function fromRequest(
        DelegationRequest $request,
        string $agentId,
        string $status,
        mixed $output = null,
        array $diagnostics = [],
        array $evidence = []
    ): self {
        $data = $request->toArray();

        return new self([
            'delegation_id' => $request->id(),
            'agent_id' => $agentId,
            'status' => $status,
            'output' => $output,
            'evidence' => $evidence,
            'diagnostics' => $diagnostics,
            'correlation_id' => $data['correlation_id'] ?? '',
            'causation_id' => $data['causation_id'] ?? '',
            'trace_id' => $data['trace_id'] ?? '',
        ]);
    }

    protected function defaults(): array
    {
        return [
            'delegation_id' => '',
            'agent_id' => '',
            'status' => DelegationStatus::COMPLETED,
            'output' => null,
            'evidence' => [],
            'diagnostics' => [],
            'messages' => [],
            'correlation_id' => '',
            'causation_id' => '',
            'trace_id' => '',
            'metadata' => [],
        ];
    }
}
