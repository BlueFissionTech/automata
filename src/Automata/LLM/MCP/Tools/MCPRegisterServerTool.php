<?php

namespace BlueFission\Automata\LLM\MCP\Tools;

use BlueFission\DevElation as Dev;

class MCPRegisterServerTool extends MCPToolBase
{
    protected string $_name = 'MCPRegisterServer';
    protected string $_description = 'Register an MCP server configuration for later discovery or tool calls.';

    public function execute($input): string
    {
        $payload = $this->decodeInput($input);
        $name = $payload['name'] ?? null;
        $url = $payload['url'] ?? null;

        if (!$name || !$url) {
            $result = ['error' => 'missing_name_or_url'];
            return $this->encodeOutput($result);
        }

        $options = $payload['options'] ?? [];
        $this->_client->registerServer((string)$name, (string)$url, is_array($options) ? $options : []);

        $result = [
            'status' => 'registered',
            'name' => $name,
            'url' => $url,
        ];

        $result = Dev::apply('automata.llm.mcp.tools.register.result', $result);
        Dev::do('automata.llm.mcp.tools.register.complete', ['result' => $result]);

        return $this->encodeOutput($result);
    }
}
