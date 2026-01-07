<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\Clients\ClaudeClient;
use BlueFission\Automata\LLM\Clients\GrokClient;
use BlueFission\Automata\LLM\Prompts\Prompt;

class ClaudeGrokClientTest extends TestCase
{
    public function testClaudeClientReturnsMockReply(): void
    {
        $client = new ClaudeClient('test-key');
        $prompt = new Prompt('Test input');

        $reply = $client->complete($prompt);

        $this->assertTrue($reply->success());
        $msg = $reply->messages()->get(0);
        $this->assertStringContainsString('Mock Claude completion', $msg);
    }

    public function testGrokClientReturnsMockReply(): void
    {
        $client = new GrokClient('test-key');
        $prompt = new Prompt('Another test input');

        $reply = $client->respond($prompt);

        $this->assertTrue($reply->success());
        $msg = $reply->messages()->get(0);
        $this->assertStringContainsString('Mock Grok response', $msg);
    }
}

