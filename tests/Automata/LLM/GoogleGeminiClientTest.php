<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\Clients\GoogleGeminiClient;

class GoogleGeminiClientTest extends TestCase
{
    private function loadEnv(): void
    {
        if (getenv('GEMINI_API_KEY') || getenv('GOOGLE_GEMINI_API_KEY')) {
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
        $apiKey = getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_GEMINI_API_KEY') ?: '';
        $flag = getenv('AUTOMATA_LIVE_LLM_TESTS') ?: '';

        if ($apiKey === '') {
            return false;
        }

        return filter_var($flag, FILTER_VALIDATE_BOOLEAN) === true;
    }

    public function testGenerateRespondsWhenLiveKeyPresent(): void
    {
        if (!$this->canRunLive()) {
            $this->markTestSkipped('GEMINI_API_KEY (or GOOGLE_GEMINI_API_KEY) and AUTOMATA_LIVE_LLM_TESTS=true are required for live connector tests.');
        }

        $apiKey = getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_GEMINI_API_KEY');
        $client = new GoogleGeminiClient($apiKey);
        $reply = $client->generate('Reply with "ready".', [
            'max_tokens' => 16,
            'temperature' => 0,
        ]);

        $this->assertTrue($reply->success());
        $message = $reply->messages()->get(0);
        $this->assertIsString($message);
        $this->assertNotSame('', $message);
    }
}
