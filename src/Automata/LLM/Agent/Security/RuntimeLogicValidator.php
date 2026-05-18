<?php

namespace BlueFission\Automata\LLM\Agent\Security;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolExecutionResult;
use BlueFission\DevElation as Dev;
use BlueFission\Str;

class RuntimeLogicValidator
{
    protected LpciScanner $scanner;
    protected array $audit = [];

    public function __construct(?LpciScanner $scanner = null)
    {
        $this->scanner = $scanner ?: new LpciScanner();
    }

    /**
     * Sanitize one text surface and append the scan result to the audit trail.
     */
    public function sanitizeText(string $content, array $context = []): array
    {
        $result = $this->scanner->sanitize($content, $context);
        $audit = [
            'surface' => $context['surface'] ?? 'text',
            'status' => $result['status'],
            'findings' => $result['findings'],
        ];
        $this->audit[] = $audit;

        Dev::do('automata.llm.agent.security.lpci_scan', $audit);

        return $result;
    }

    /**
     * Sanitize tool output payloads before they re-enter model context.
     */
    public function sanitizeToolResult(ToolExecutionResult $result, array $context = []): ToolExecutionResult
    {
        $payload = $result->payload();
        if (Arr::is($payload) && Arr::hasKey($payload, 'output') && Str::is($payload['output'])) {
            $scan = $this->sanitizeText($payload['output'], $this->mergeContext($context, ['surface' => 'tool_output']));
            $payload['output'] = $scan['content'];
            return $result->withPayload($payload, ['lpci' => $scan]);
        }

        if (Str::is($payload)) {
            $scan = $this->sanitizeText($payload, $this->mergeContext($context, ['surface' => 'tool_output']));
            return $result->withPayload($scan['content'], ['lpci' => $scan]);
        }

        return $result;
    }

    /**
     * Return all runtime scan audit entries.
     */
    public function auditTrail(): array
    {
        return $this->audit;
    }

    /**
     * Merge runtime scan context without raw PHP array helpers.
     */
    protected function mergeContext(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $base[$key] = $value;
        }

        return $base;
    }
}
