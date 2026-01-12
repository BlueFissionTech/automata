<?php

namespace BlueFission\Automata\LLM\MCP\Tools;

use BlueFission\DevElation as Dev;

class MCPResourceTool extends MCPToolBase
{
    protected string $_name = 'MCPResource';
    protected string $_description = 'Read an MCP resource by URI.';

    public function execute($input): string
    {
        $payload = $this->decodeInput($input);
        $server = $payload['server'] ?? null;
        $uri = $payload['uri'] ?? null;

        if (!$server || !$uri) {
            $result = ['error' => 'missing_server_or_uri'];
            return $this->encodeOutput($result);
        }

        $params = $payload['params'] ?? [];
        $result = $this->_client->readResource($server, $uri, is_array($params) ? $params : []);

        $result = Dev::apply('automata.llm.mcp.tools.resource.result', $result);
        Dev::do('automata.llm.mcp.tools.resource.complete', ['server' => $server, 'uri' => $uri, 'result' => $result]);

        return $this->encodeOutput($result);
    }
}
