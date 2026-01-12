<?php

namespace BlueFission\Automata\LLM\MCP\Tools;

use BlueFission\DevElation as Dev;

class MCPRequestTool extends MCPToolBase
{
    protected string $_name = 'MCPRequest';
    protected string $_description = 'Send a raw MCP method request with params to a server.';

    public function execute($input): string
    {
        $payload = $this->decodeInput($input);
        $server = $payload['server'] ?? null;
        $method = $payload['method'] ?? null;
        $params = $payload['params'] ?? [];

        if (!$server || !$method) {
            $result = ['error' => 'missing_server_or_method'];
            return $this->encodeOutput($result);
        }

        $result = $this->_client->call($server, $method, is_array($params) ? $params : []);

        $result = Dev::apply('automata.llm.mcp.tools.request.result', $result);
        Dev::do('automata.llm.mcp.tools.request.complete', ['server' => $server, 'method' => $method, 'result' => $result]);

        return $this->encodeOutput($result);
    }
}
