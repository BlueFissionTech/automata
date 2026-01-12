<?php

namespace BlueFission\Automata\LLM\MCP\Tools;

use BlueFission\DevElation as Dev;

class MCPDiscoveryTool extends MCPToolBase
{
    protected string $_name = 'MCPDiscovery';
    protected string $_description = 'List MCP servers, resources, tools, or templates.';

    public function execute($input): string
    {
        $payload = $this->decodeInput($input);
        $action = strtolower((string)($payload['action'] ?? 'servers'));
        $server = $payload['server'] ?? null;

        $result = [];

        switch ($action) {
            case 'servers':
                $result = $this->_client->listServers();
                break;
            case 'resources':
                $result = $server ? $this->_client->listResources($server) : ['error' => 'missing_server'];
                break;
            case 'templates':
                $result = $server ? $this->_client->listResourceTemplates($server) : ['error' => 'missing_server'];
                break;
            case 'tools':
                $result = $server ? $this->_client->listTools($server) : ['error' => 'missing_server'];
                break;
            default:
                $result = ['error' => 'unknown_action', 'action' => $action];
        }

        $result = Dev::apply('automata.llm.mcp.tools.discovery.result', $result);
        Dev::do('automata.llm.mcp.tools.discovery.complete', ['action' => $action, 'result' => $result]);

        return $this->encodeOutput($result);
    }
}
