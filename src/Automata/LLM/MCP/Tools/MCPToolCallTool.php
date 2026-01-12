<?php

namespace BlueFission\Automata\LLM\MCP\Tools;

use BlueFission\DevElation as Dev;

class MCPToolCallTool extends MCPToolBase
{
    protected string $_name = 'MCPToolCall';
    protected string $_description = 'Invoke an MCP tool by name with arguments.';

    public function execute($input): string
    {
        $payload = $this->decodeInput($input);
        $server = $payload['server'] ?? null;
        $tool = $payload['tool'] ?? null;
        $arguments = $payload['arguments'] ?? [];

        if (!$server || !$tool) {
            $result = ['error' => 'missing_server_or_tool'];
            return $this->encodeOutput($result);
        }

        $result = $this->_client->callTool($server, $tool, is_array($arguments) ? $arguments : []);

        $result = Dev::apply('automata.llm.mcp.tools.toolcall.result', $result);
        Dev::do('automata.llm.mcp.tools.toolcall.complete', ['server' => $server, 'tool' => $tool, 'result' => $result]);

        return $this->encodeOutput($result);
    }
}
