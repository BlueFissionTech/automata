<?php

namespace BlueFission\Tests\Automata\LLM\MCP;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Automata\LLM\MCP\IMCPTransport;
use BlueFission\Automata\LLM\MCP\Tools\MCPDiscoveryTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPResourceTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPToolCallTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPRequestTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPRegisterServerTool;

class MCPToolsTransportStub implements IMCPTransport
{
    public array $last = [];

    public function send(array $server, array $payload): array
    {
        $this->last = [
            'server' => $server,
            'payload' => $payload,
        ];

        return [
            'payload' => $payload,
            'server' => $server,
        ];
    }
}

class MCPToolsTest extends TestCase
{
    public function testRegisterServerToolAddsServer(): void
    {
        $transport = new MCPToolsTransportStub();
        $client = new MCPClient($transport);

        $tool = new MCPRegisterServerTool($client);
        $payload = json_encode(['name' => 'alpha', 'url' => 'http://localhost:3333']);
        $result = json_decode($tool->execute($payload), true);

        $this->assertSame('registered', $result['status']);
        $this->assertArrayHasKey('alpha', $client->listServers());
    }

    public function testDiscoveryToolListsServers(): void
    {
        $transport = new MCPToolsTransportStub();
        $client = new MCPClient($transport);
        $client->registerServer('alpha', 'http://localhost:3333');

        $tool = new MCPDiscoveryTool($client);
        $result = json_decode($tool->execute(json_encode(['action' => 'servers'])), true);

        $this->assertArrayHasKey('alpha', $result);
    }

    public function testResourceAndToolCallsReturnPayloads(): void
    {
        $transport = new MCPToolsTransportStub();
        $client = new MCPClient($transport);
        $client->registerServer('alpha', 'http://localhost:3333');

        $resourceTool = new MCPResourceTool($client);
        $resource = json_decode($resourceTool->execute(json_encode([
            'server' => 'alpha',
            'uri' => 'memory://demo',
        ])), true);

        $this->assertSame('resources/read', $resource['payload']['method']);

        $toolCall = new MCPToolCallTool($client);
        $toolResponse = json_decode($toolCall->execute(json_encode([
            'server' => 'alpha',
            'tool' => 'echo',
            'arguments' => ['message' => 'hi'],
        ])), true);

        $this->assertSame('tools/call', $toolResponse['payload']['method']);

        $requestTool = new MCPRequestTool($client);
        $request = json_decode($requestTool->execute(json_encode([
            'server' => 'alpha',
            'method' => 'tools/list',
        ])), true);

        $this->assertSame('tools/list', $request['payload']['method']);
    }
}
