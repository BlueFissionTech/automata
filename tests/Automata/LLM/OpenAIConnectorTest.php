<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\Connectors\OpenAI;

class OpenAIConnectorTest extends TestCase
{
    private function loadEnv(): void
    {
        if (getenv('OPENAI_API_KEY')) {
            return;
        }

        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            $value = trim($value, "\"'");
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function canRunLive(): bool
    {
        $this->loadEnv();
        $apiKey = getenv('OPENAI_API_KEY') ?: '';
        $flag = getenv('AUTOMATA_LIVE_LLM_TESTS') ?: '';

        if ($apiKey === '') {
            return false;
        }

        return filter_var($flag, FILTER_VALIDATE_BOOLEAN) === true;
    }

    public function testChatRespondsWhenLiveKeyPresent(): void
    {
        if (!$this->canRunLive()) {
            $this->markTestSkipped('OPENAI_API_KEY and AUTOMATA_LIVE_LLM_TESTS=true are required for live connector tests.');
        }

        $model = getenv('OPENAI_CHAT_MODEL') ?: 'gpt-4o-mini';

        $connector = new OpenAI(getenv('OPENAI_API_KEY'));
        $response = $connector->chat('Reply with "ready".', [
            'model' => $model,
            'max_tokens' => 8,
            'temperature' => 0,
        ]);

        $this->assertIsArray($response);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertArrayHasKey('choices', $response);
        $this->assertNotEmpty($response['choices']);
    }
}
