<?php

namespace BlueFission\Automata\LLM\MCP;

use BlueFission\DevElation as Dev;

class MCPClient
{
    protected ServerRegistry $_registry;
    protected IMCPTransport $_transport;

    public function __construct(?IMCPTransport $transport = null, ?ServerRegistry $registry = null)
    {
        $this->_transport = $transport ?? new HttpTransport();
        $this->_registry = $registry ?? new ServerRegistry();
    }

    public function registerServer(string $name, string $url, array $options = []): void
    {
        $name = Dev::apply('automata.llm.mcp.client.register.name', $name);
        $url = Dev::apply('automata.llm.mcp.client.register.url', $url);
        $options = Dev::apply('automata.llm.mcp.client.register.options', $options);

        $this->_registry->register($name, $url, $options);
        Dev::do('automata.llm.mcp.client.registered', ['name' => $name, 'url' => $url, 'options' => $options]);
    }

    /**
     * @return array<string, array>
     */
    public function listServers(): array
    {
        return Dev::apply('automata.llm.mcp.client.list_servers', $this->_registry->all());
    }

    public function call(string $server, string $method, array $params = []): array
    {
        $server = Dev::apply('automata.llm.mcp.client.call.server', $server);
        $method = Dev::apply('automata.llm.mcp.client.call.method', $method);
        $params = Dev::apply('automata.llm.mcp.client.call.params', $params);

        $serverConfig = $this->_registry->get($server);
        if (!$serverConfig) {
            $result = ['error' => 'unknown_server', 'server' => $server];
            Dev::do('automata.llm.mcp.client.call.error', ['result' => $result]);
            return $result;
        }

        $payload = [
            'jsonrpc' => '2.0',
            'id' => uniqid('mcp_', true),
            'method' => $method,
            'params' => $params,
        ];

        $result = $this->_transport->send($serverConfig, $payload);
        $result = Dev::apply('automata.llm.mcp.client.call.result', $result);
        Dev::do('automata.llm.mcp.client.call.complete', [
            'server' => $server,
            'method' => $method,
            'result' => $result,
        ]);

        return $result;
    }

    public function listResources(string $server, array $params = []): array
    {
        return $this->call($server, 'resources/list', $params);
    }

    public function listResourceTemplates(string $server, array $params = []): array
    {
        return $this->call($server, 'resources/templates/list', $params);
    }

    public function readResource(string $server, string $uri, array $params = []): array
    {
        $payload = array_merge(['uri' => $uri], $params);
        return $this->call($server, 'resources/read', $payload);
    }

    public function listTools(string $server, array $params = []): array
    {
        return $this->call($server, 'tools/list', $params);
    }

    public function callTool(string $server, string $tool, array $arguments = []): array
    {
        $payload = [
            'name' => $tool,
            'arguments' => $arguments,
        ];

        return $this->call($server, 'tools/call', $payload);
    }
}
