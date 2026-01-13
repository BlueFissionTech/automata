<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\SimpleClients\GoogleGeminiClient;

class GeminiResponseStub
{
    private string $text;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    public function text(): string
    {
        return $this->text;
    }
}

class GeminiProStub
{
    public array $history = [];
    public array $sent = [];

    public function generateContent($input, array $config = []): GeminiResponseStub
    {
        $this->sent[] = ['input' => $input, 'config' => $config];
        return new GeminiResponseStub("generated: {$input}");
    }

    public function sendMessage($input, array $config = []): GeminiResponseStub
    {
        $this->sent[] = ['input' => $input, 'config' => $config];
        return new GeminiResponseStub("chat: {$input}");
    }

    public function startChat(array $history = []): void
    {
        $this->history = $history;
    }
}

class GeminiEmbeddingStub
{
    public function embedContent($input): array
    {
        return ['embedding' => ['values' => ["{$input}-v1", "{$input}-v2"]]];
    }
}

class GeminiClientStub
{
    public GeminiProStub $pro;

    public function __construct()
    {
        $this->pro = new GeminiProStub();
    }

    public function geminiPro(): GeminiProStub
    {
        return $this->pro;
    }

    public function embeddingModel(): GeminiEmbeddingStub
    {
        return new GeminiEmbeddingStub();
    }
}

class GoogleGeminiClientTest extends TestCase
{
    public function testGenerateUsesStubbedClient(): void
    {
        $clientStub = new GeminiClientStub();
        $client = new GoogleGeminiClient('test-key', $clientStub);

        $result = $client->generate('hello', ['top_p' => 0.5]);

        $this->assertSame('generated: hello', $result);
        $this->assertSame(
            ['input' => 'hello', 'config' => ['top_p' => 0.5]],
            $clientStub->pro->sent[0]
        );
    }

    public function testRespondUsesHistoryAndEmbeddings(): void
    {
        $clientStub = new GeminiClientStub();
        $client = new GoogleGeminiClient('test-key', $clientStub);
        $history = [['role' => 'user', 'content' => 'past']];

        $result = $client->respond('hi', ['max_tokens' => 10], $history);
        $embedding = $client->embeddings('text');

        $this->assertSame('chat: hi', $result);
        $this->assertSame($history, $clientStub->pro->history);
        $this->assertSame(['embedding' => ['values' => ['text-v1', 'text-v2']]], $embedding);
    }
}
