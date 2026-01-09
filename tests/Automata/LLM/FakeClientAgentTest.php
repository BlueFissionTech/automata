<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Tools\BaseTool;

class FakeClient implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $reply = new Reply();
        $reply->addMessage('generated:' . (string)$input, true);
        return $reply;
    }

    public function complete($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('completed:' . (string)$input, true);
        return $reply;
    }

    public function respond($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('responded:' . (string)$input, true);
        return $reply;
    }
}

class DummyTool extends BaseTool
{
    public function __construct()
    {
        $this->description = 'Dummy tool for testing';
        $this->name = 'dummy';
    }
}

class FakeClientAgentTest extends TestCase
{
    public function testAgentConstructsWithClient(): void
    {
        $client = new FakeClient();
        $agent  = new Agent($client);

        $agent->registerTool('dummy', new DummyTool());
        $this->assertInstanceOf(Agent::class, $agent);
    }
}
