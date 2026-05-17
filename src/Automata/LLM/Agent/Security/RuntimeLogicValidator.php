<?php

namespace BlueFission\Automata\LLM\Agent\Security;

use BlueFission\Automata\LLM\Agent\ToolExecutionResult;
use BlueFission\DevElation as Dev;

class RuntimeLogicValidator
{
    protected LpciScanner $scanner;
    protected array $audit = [];

    public function __construct(?LpciScanner $scanner = null)
    {
        $this->scanner = $scanner ?: new LpciScanner();
    }

    public function sanitizeText(string $content, array $context = []): array
    {
        $result = $this->scanner->sanitize($content, $context);
        $this->audit[] = [
            'surface' => $context['surface'] ?? 'text',
            'status' => $result['status'],
            'findings' => $result['findings'],
        ];

        Dev::do('automata.llm.agent.security.lpci_scan', end($this->audit));

        return $result;
    }

    public function sanitizeToolResult(ToolExecutionResult $result, array $context = []): ToolExecutionResult
    {
        $payload = $result->payload();
        if (is_array($payload) && isset($payload['output']) && is_string($payload['output'])) {
            $scan = $this->sanitizeText($payload['output'], array_replace($context, ['surface' => 'tool_output']));
            $payload['output'] = $scan['content'];
            return $result->withPayload($payload, ['lpci' => $scan]);
        }

        if (is_string($payload)) {
            $scan = $this->sanitizeText($payload, array_replace($context, ['surface' => 'tool_output']));
            return $result->withPayload($scan['content'], ['lpci' => $scan]);
        }

        return $result;
    }

    public function auditTrail(): array
    {
        return $this->audit;
    }
}
