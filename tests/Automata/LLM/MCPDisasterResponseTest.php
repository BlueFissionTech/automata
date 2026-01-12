<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Automata\LLM\MCP\IMCPTransport;
use BlueFission\Automata\LLM\MCP\Tools\MCPRequestTool;

class MCPDisasterResponseTransportStub implements IMCPTransport
{
    public array $last = [];

    public function send(array $server, array $payload): array
    {
        $this->last = [
            'server' => $server,
            'payload' => $payload,
        ];

        return [
            'result' => [
                'status' => 'ok',
                'summary' => 'mocked disaster response payload',
            ],
            'payload' => $payload,
        ];
    }
}

class MCPDisasterResponseTest extends TestCase
{
    public function testMcpRequestToolSupportsDisasterResponsePayload(): void
    {
        $transport = new MCPDisasterResponseTransportStub();
        $client = new MCPClient($transport);

        $client->registerServer('logistics', 'http://localhost:3333', [
            'path' => 'mcp',
        ]);

        $tool = new MCPRequestTool($client);
        $input = json_encode([
            'server' => 'logistics',
            'method' => 'resources/read',
            'params' => [
                'uri' => 'disaster://coastal-county/dispatch',
                'seed' => 'SEED_A',
                'time_step' => 3,
            ],
        ]);

        $response = json_decode($tool->execute($input), true);

        $this->assertSame('resources/read', $response['payload']['method']);
        $this->assertSame('disaster://coastal-county/dispatch', $response['payload']['params']['uri']);
        $this->assertSame('ok', $response['result']['status']);
    }
}
