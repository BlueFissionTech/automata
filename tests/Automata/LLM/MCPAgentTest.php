<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Automata\LLM\MCP\IMCPTransport;

class MCPAgentClientStub implements IClient
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

class MCPAgentTransportStub implements IMCPTransport
{
    public function send(array $server, array $payload): array
    {
        return ['payload' => $payload, 'server' => $server];
    }
}

class MCPAgentTest extends TestCase
{
    public function testAgentRegistersMcpTools(): void
    {
        $agent = new Agent(new MCPAgentClientStub());
        $client = new MCPClient(new MCPAgentTransportStub());

        $agent->registerMcpClient($client);

        $ref = new \ReflectionClass($agent);
        $prop = $ref->getProperty('tools');
        $prop->setAccessible(true);
        $tools = $prop->getValue($agent);

        $this->assertArrayHasKey('MCPRegisterServer', $tools);
        $this->assertArrayHasKey('MCPDiscovery', $tools);
        $this->assertArrayHasKey('MCPResource', $tools);
        $this->assertArrayHasKey('MCPToolCall', $tools);
        $this->assertArrayHasKey('MCPRequest', $tools);
    }
}
