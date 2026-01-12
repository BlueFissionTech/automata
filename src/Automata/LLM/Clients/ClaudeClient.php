<?php

namespace BlueFission\Automata\LLM\Clients;

use BlueFission\Automata\LLM\Prompts\IPrompt;
use BlueFission\Automata\LLM\Reply;

/**
 * ClaudeClient
 *
 * Thin wrapper for an Anthropic/Claude-style completion API.
 * This class is intentionally generic and does not make real HTTP
 * calls in tests; it exists as a structured integration point.
 *
 * TODO: Implement real Anthropic/Claude HTTP calls here using
 *       BlueFission\Services\Client (or a dedicated connector)
 *       once API wiring and configuration are defined.
 */
class ClaudeClient implements IClient
{
    private string $_apiKey;
    private string $_baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.anthropic.com')
    {
        $this->_apiKey  = $apiKey;
        $this->_baseUrl = rtrim($baseUrl, '/');
    }

    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        return $this->complete($input, $config);
    }

    public function complete($input, $config = []): Reply
    {
        if ($input instanceof IPrompt) {
            $input = $input->prompt();
        }

        // In a real implementation, map $config and $input into an Anthropic request body.
        // Here we simply shape a fake response for offline use.
        $body = [
            'prompt' => (string)$input,
        ] + $config;

        // $response = $this->post($body, 'v1/complete');
        $response = [
            'completion' => 'Mock Claude completion for: ' . mb_substr($body['prompt'], 0, 40),
        ];

        return $this->processResponse($response);
    }

    public function respond($input, $config = []): Reply
    {
        // For now, treat chat-style "respond" the same as "complete".
        return $this->complete($input, $config);
    }

    private function processResponse($response): Reply
    {
        $reply = new Reply();

        if (is_array($response) && isset($response['completion'])) {
            $reply->addMessage((string)$response['completion'], true);
        } else {
            $reply->addMessage('No response', false);
        }

        return $reply;
    }
}
