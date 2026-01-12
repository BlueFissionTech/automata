<?php

namespace BlueFission\Automata\LLM\MCP\Tools;

use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Automata\LLM\Tools\ITool;
use BlueFission\DevElation as Dev;

abstract class MCPToolBase implements ITool
{
    protected MCPClient $_client;
    protected string $_name = '';
    protected string $_description = '';

    public function __construct(MCPClient $client)
    {
        $this->_client = $client;
    }

    public function name(): string
    {
        return $this->_name;
    }

    public function description(): string
    {
        return $this->_description;
    }

    protected function decodeInput($input): array
    {
        $input = Dev::apply('automata.llm.mcp.tools.decode.input', $input);

        if (is_array($input)) {
            return $input;
        }

        if (is_string($input)) {
            $trimmed = trim($input);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            return ['query' => $input];
        }

        return ['input' => $input];
    }

    protected function encodeOutput($payload): string
    {
        $payload = Dev::apply('automata.llm.mcp.tools.encode.output', $payload);
        return json_encode($payload);
    }
}
