<?php

namespace BlueFission\Automata\LLM\Clients;
use BlueFission\Automata\LLM\Prompts\IPrompt;
use BlueFission\Automata\LLM\Reply;

/**
 * GrokClient
 *
 * Generic client shell for a Grok-style LLM API. Like ClaudeClient,
 * this is structured for integration but uses a mock response by default
 * to allow offline testing.
 *
 * TODO: Implement real Grok HTTP calls here using
 *       BlueFission\Services\Client (or a dedicated connector)
 *       once API wiring and configuration are defined.
 */
class GrokClient implements IClient
{
    private string $_apiKey;
    private string $_baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.grok.com')
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

        $body = [
            'prompt' => (string)$input,
        ] + $config;

        // $response = $this->post($body, 'v1/chat/completions');
        $response = [
            'message' => 'Mock Grok response for: ' . mb_substr($body['prompt'], 0, 40),
        ];

        return $this->processResponse($response);
    }

    public function respond($input, $config = []): Reply
    {
        return $this->complete($input, $config);
    }

    private function processResponse($response): Reply
    {
        $reply = new Reply();

        if (is_array($response) && isset($response['message'])) {
            $reply->addMessage((string)$response['message'], true);
        } else {
            $reply->addMessage('No response', false);
        }

        return $reply;
    }
}
