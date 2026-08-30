<?php

namespace BlueFission\Tests\Automata\LLM\MCP;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Automata\LLM\MCP\IMCPTransport;

class MCPClientTransportStub implements IMCPTransport
{
    public array $last = [];

    public function send(array $server, array $payload): array
    {
        $this->last = [
            'server' => $server,
            'payload' => $payload,
        ];

        return [
            'result' => ['ok' => true],
            'payload' => $payload,
            'server' => $server,
        ];
    }
}

class MCPClientTest extends TestCase
{
    public function testRegisterAndListServers(): void
    {
        $transport = new MCPClientTransportStub();
        $client = new MCPClient($transport);

        $client->registerServer('demo', 'http://localhost:3333', ['path' => 'mcp']);

        $servers = $client->listServers();

        $this->assertArrayHasKey('demo', $servers);
        $this->assertSame('http://localhost:3333', $servers['demo']['url']);
        $this->assertSame('mcp', $servers['demo']['path']);
    }

    public function testListResourcesUsesTransport(): void
    {
        $transport = new MCPClientTransportStub();
        $client = new MCPClient($transport);

        $client->registerServer('demo', 'http://localhost:3333');
        $client->listResources('demo');

        $this->assertSame('resources/list', $transport->last['payload']['method']);
    }

    public function testReadResourceUsesTransport(): void
    {
        $transport = new MCPClientTransportStub();
        $client = new MCPClient($transport);

        $client->registerServer('demo', 'http://localhost:3333');
        $client->readResource('demo', 'memory://example');

        $this->assertSame('resources/read', $transport->last['payload']['method']);
        $this->assertSame('memory://example', $transport->last['payload']['params']['uri']);
    }
}
