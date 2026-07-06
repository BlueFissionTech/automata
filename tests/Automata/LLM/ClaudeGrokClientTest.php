<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\SimpleClients\ClaudeClient;
use BlueFission\SimpleClients\GrokClient;
use BlueFission\Automata\LLM\Prompts\Prompt;
use BlueFission\Net\HTTP;

class ClaudeGrokClientTest extends TestCase
{
    public function testClaudeClientReturnsMockReply(): void
    {
        $transport = new class {
            public function request(string $method, string $url, array $headers = [], $body = null): array
            {
                return [
                    'status' => 200,
                    'body' => HTTP::jsonEncode([
                        'content' => [
                            ['text' => 'Claude mock completion for local contract test'],
                        ],
                    ]),
                    'headers' => [],
                ];
            }
        };
        $client = new ClaudeClient('test-key', 'https://api.anthropic.com', $transport);
        $prompt = new Prompt('Test input');

        $reply = $client->complete($prompt);

        $this->assertIsArray($reply);
        $this->assertArrayHasKey('completion', $reply);
        $this->assertStringContainsString('Claude mock completion', $reply['completion']);
    }

    public function testGrokClientReturnsMockReply(): void
    {
        $transport = new class {
            public function request(string $method, string $url, array $headers = [], $body = null): array
            {
                return [
                    'status' => 200,
                    'body' => HTTP::jsonEncode([
                        'choices' => [
                            [
                                'message' => [
                                    'content' => 'Grok mock response for local contract test',
                                ],
                            ],
                        ],
                    ]),
                    'headers' => [],
                ];
            }
        };
        $client = new GrokClient('test-key', 'https://api.x.ai', $transport);
        $prompt = new Prompt('Another test input');

        $reply = $client->respond($prompt);

        $this->assertIsArray($reply);
        $this->assertArrayHasKey('message', $reply);
        $this->assertStringContainsString('Grok mock response', $reply['message']);
    }
}

