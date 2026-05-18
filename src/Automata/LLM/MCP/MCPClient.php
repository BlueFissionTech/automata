<?php

namespace BlueFission\Automata\LLM\MCP;

use BlueFission\Automata\LLM\Agent\Governance\HumanReviewGate;
use BlueFission\Automata\LLM\Agent\Governance\TaskCallMonitor;
use BlueFission\Automata\LLM\Agent\Governance\TaskCallPolicy;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\DevElation as Dev;

class MCPClient
{
    protected ServerRegistry $_registry;
    protected IMCPTransport $_transport;
    protected ?TaskTrace $_taskTrace = null;
    protected ?TaskCallMonitor $_callMonitor = null;

    public function __construct(?IMCPTransport $transport = null, ?ServerRegistry $registry = null)
    {
        $this->_transport = $transport ?? new HttpTransport();
        $this->_registry = $registry ?? new ServerRegistry();
    }

    /**
     * Attach a task trace so MCP traffic rolls into CPCT and governance views.
     */
    public function useTaskTrace(?TaskTrace $trace): void
    {
        $this->_taskTrace = $trace;
        if ($this->_callMonitor) {
            $this->_callMonitor->useTrace($trace);
        }
    }

    /**
     * Attach a preconfigured task-call monitor.
     */
    public function useCallMonitor(TaskCallMonitor $monitor): void
    {
        $this->_callMonitor = $monitor;
        if ($this->_taskTrace) {
            $this->_callMonitor->useTrace($this->_taskTrace);
        }
    }

    /**
     * Attach a human review gate and optional MCP call policy.
     */
    public function useHumanReviewGate(HumanReviewGate $gate, TaskCallPolicy|array|null $policy = null): void
    {
        $this->_callMonitor = new TaskCallMonitor($this->_taskTrace, $policy ?? [], $gate);
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

        $observed = $this->callMonitor()->observe(
            TaskTraceSpan::KIND_MCP,
            $server . '.' . $method,
            function (array $request) use ($serverConfig): array {
                return $this->_transport->send($serverConfig, $request['payload']);
            },
            [
                'server' => $server,
                'method' => $method,
                'params' => $params,
                'payload' => $payload,
            ],
            [
                'protocol' => 'mcp',
                'jsonrpc' => '2.0',
                'server' => $server,
                'method' => $method,
            ]
        );

        if (!$observed['ok']) {
            $result = [
                'error' => $observed['error']['code'] ?? $observed['status'],
                'governance' => $observed,
            ];
            Dev::do('automata.llm.mcp.client.call.error', ['result' => $result]);
            return $result;
        }

        $result = $observed['payload'];
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
        $payload = ToolDefinition::mergeConfig(['uri' => $uri], $params);
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

    /**
     * Return the active monitor, creating a default observer when needed.
     */
    protected function callMonitor(): TaskCallMonitor
    {
        if (!$this->_callMonitor) {
            $this->_callMonitor = new TaskCallMonitor($this->_taskTrace);
        }

        return $this->_callMonitor;
    }
}
