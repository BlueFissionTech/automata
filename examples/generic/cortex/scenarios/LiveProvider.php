<?php

namespace CortexExample\Scenarios;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Connections\Curl;
use BlueFission\SimpleClients\Cloud\HttpClient;
use RuntimeException;

/**
 * Opt-in, bounded provider transport through the first-party HTTP client.
 *
 * Credentials are read from the process environment only. Returned receipts
 * contain model, request identity, token usage and elapsed time; never headers,
 * credentials or provider error bodies. No tool integrations are enabled.
 */
final class LiveProvider
{
    public const CONFIG = [
        'openai' => ['key' => 'OPENAI_API_KEY', 'model' => 'gpt-4.1-mini-2025-04-14',
            'url' => 'https://api.openai.com/v1/chat/completions'],
        'gemini' => ['key' => 'GOOGLE_GEMINI_API_KEY', 'model' => 'gemini-3.5-flash-lite',
            'url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent'],
        'pokee' => ['key' => 'POKEE_API_KEY', 'model' => 'pokee-isaac',
            'url' => 'https://api.pokee.ai/v1/chat/completions'],
    ];
    public array $receipts = [];
    private int $calls = 0;

    /** Only the three reviewed provider destinations are accepted. */
    public function __construct(private readonly string $provider)
    {
        if (!Arr::hasKey(self::CONFIG, $provider)) { throw new RuntimeException('Unknown provider.'); }
    }

    /** Maximum eight calls per instance, 384 output tokens per call, no retries. */
    public function propose(array $input): array
    {
        $config = self::CONFIG[$this->provider];
        $key = getenv($config['key']);
        if (!Str::is($key) || Str::make($key)->trim()->val() === '') {
            throw new RuntimeException('Provider credential unavailable.');
        }
        if ($this->calls >= 8) { throw new RuntimeException('Live call budget exhausted.'); }
        ++$this->calls;
        $prompt = "You propose decisions for a synthetic agent test. Return exactly one JSON object, no prose or markdown. "
            . "Every response MUST contain both action (string) and completed (boolean false). "
            . "Optional fields: reference (string), destination (string), take (integer). Omit only optional fields not needed. "
            . "For booking: missing reference requires action ask_reference; otherwise action plan_checkin with the current reference. "
            . "For operations: an existing receiver receipt requires reconcile, and revoked approval requires wait. Never repeat an observed effect. "
            . "For a game: action move, choose take 1..3, no more than remaining stones; last stone wins against optimal play. "
            . "For NPC: respect the player's latest goal; an unsafe route requires wait; a safe escort requires action escort and its destination. "
            . "No proposal has executed. Public scenario state: " . json_encode($input, JSON_THROW_ON_ERROR);
        $headers = ['Content-Type: application/json'];
        if ($this->provider === 'gemini') {
            $headers[] = 'x-goog-api-key: ' . $key;
            $body = ['contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['maxOutputTokens' => 384, 'temperature' => 0,
                    'responseMimeType' => 'application/json']];
        } else {
            $headers[] = 'Authorization: Bearer ' . $key;
            $body = ['model' => $config['model'], 'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 384, 'stream' => false];
        }
        $http = new HttpClient(new Curl(['timeout' => 45, 'connect_timeout' => 10,
            'verify_ssl' => true, 'validate_host' => true, 'verbose' => false]));
        $started = hrtime(true);
        $response = $http->request('POST', $config['url'], $headers, $body);
        $elapsed = Num::make(hrtime(true))->subtract($started)->divide(1000000)->val();
        $record = ['provider' => $this->provider, 'requested_model' => $config['model'],
            'http_status' => $response['status'], 'elapsed_ms' => $elapsed,
            'cost' => null, 'usage' => null, 'request_id' => null, 'model' => null,
            'error_code' => null, 'prompt_version' => '2'];
        // Retain the HTTP attempt even if parsing or credential admission fails.
        $this->receipts[] = $record;
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $error = json_decode($response['body'], true);
            $code = $error['error']['code'] ?? $error['error']['status'] ?? null;
            // Provider messages can echo credentials. Retain only known codes,
            // never arbitrary error text or raw bodies.
            if (Arr::has(['invalid_api_key', 'insufficient_quota', 'model_not_found',
                'rate_limit_exceeded', 'slow_down', 'credit_balance_exhausted',
                'organization_spend_limit_exceeded', 'project_spend_limit_exceeded',
                'organization_usage_limit_exceeded', 'usage_limit_exceeded',
                'PERMISSION_DENIED', 'NOT_FOUND', 'INVALID_ARGUMENT', 'RESOURCE_EXHAUSTED'], $code, true)) {
                $record['error_code'] = $code;
                $this->receipts[Arr::size($this->receipts) - 1] = $record;
            }
            throw new RuntimeException('Provider HTTP ' . $response['status']);
        }
        $data = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
        $text = $this->provider === 'gemini'
            ? Arr::make($data['candidates'][0]['content']['parts'] ?? [])
                ->map(static fn ($part) => $part['text'] ?? '')->join('')->val()
            : ($data['choices'][0]['message']['content'] ?? '');
        $record['usage'] = $data['usage'] ?? $data['usageMetadata'] ?? null;
        $record['request_id'] = $data['id'] ?? $data['responseId'] ?? null;
        $record['model'] = $data['model'] ?? $data['modelVersion'] ?? null;
        $this->receipts[Arr::size($this->receipts) - 1] = $record;
        $proposal = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
        if (!Arr::is($proposal)) { throw new RuntimeException('Provider returned a non-object proposal.'); }
        return $proposal;
    }
}
