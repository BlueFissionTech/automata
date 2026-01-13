<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\SimpleClients\ClaudeClient;
use BlueFission\SimpleClients\GrokClient;
use BlueFission\Automata\LLM\Prompts\Prompt;

class ClaudeGrokClientTest extends TestCase
{
    public function testClaudeClientReturnsMockReply(): void
    {
        $client = new ClaudeClient('test-key');
        $prompt = new Prompt('Test input');

        $reply = $client->complete($prompt);

        $this->assertIsArray($reply);
        $this->assertArrayHasKey('completion', $reply);
        $this->assertStringContainsString('Claude mock completion', $reply['completion']);
    }

    public function testGrokClientReturnsMockReply(): void
    {
        $client = new GrokClient('test-key');
        $prompt = new Prompt('Another test input');

        $reply = $client->respond($prompt);

        $this->assertIsArray($reply);
        $this->assertArrayHasKey('message', $reply);
        $this->assertStringContainsString('Grok mock response', $reply['message']);
    }
}

