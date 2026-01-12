<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Automata\LLM\MCP\IMCPTransport;
use BlueFission\Automata\LLM\MCP\Tools\MCPDiscoveryTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPRequestTool;

class ExampleTransport implements IMCPTransport
{
    public function send(array $server, array $payload): array
    {
        return [
            'server' => $server['name'] ?? 'unknown',
            'payload' => $payload,
            'result' => ['ok' => true],
        ];
    }
}

class ExampleClient implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $reply = new Reply();
        $reply->addMessage('generated', true);
        return $reply;
    }

    public function complete($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('completed', true);
        return $reply;
    }

    public function respond($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('responded', true);
        return $reply;
    }
}

$mcpClient = new MCPClient(new ExampleTransport());
$mcpClient->registerServer('demo', 'http://localhost:3333', ['path' => 'mcp']);

$agent = new Agent(new ExampleClient());
$agent->registerMcpClient($mcpClient);

$discovery = new MCPDiscoveryTool($mcpClient);
$servers = json_decode($discovery->execute(json_encode([
    'action' => 'servers',
])), true);

$requestTool = new MCPRequestTool($mcpClient);
$response = json_decode($requestTool->execute(json_encode([
    'server' => 'demo',
    'method' => 'tools/list',
])), true);

echo "=== MCP Agent Example ===\n\n";
echo "Registered servers:\n";
echo json_encode($servers, JSON_PRETTY_PRINT) . "\n\n";
echo "Request echo:\n";
echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
echo "Example completed.\n";
