<?php

namespace BlueFission\Automata\Security;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;

class HerdSignalContract
{
    public const DECISION_ALLOW = 'allow';
    public const DECISION_CHALLENGE = 'challenge';
    public const DECISION_RESTRICT = 'restrict';
    public const DECISION_DENY = 'deny';

    public const SENSITIVITY_PUBLIC = 'public';
    public const SENSITIVITY_INTERNAL = 'internal';
    public const SENSITIVITY_SENSITIVE = 'sensitive';

    public static function signal(array $data): array
    {
        $type = (string)($data['type'] ?? 'unknown');

        return [
            'id' => (string)($data['id'] ?? 'signal.' . $type),
            'type' => $type,
            'value' => $data['value'] ?? null,
            'score' => self::pressure($data['score'] ?? 0.0),
            'weight' => Num::max(0.0, (float)($data['weight'] ?? 1.0)),
            'confidence' => self::pressure($data['confidence'] ?? 1.0),
            'sensitivity' => self::sensitivity($data['sensitivity'] ?? self::SENSITIVITY_INTERNAL),
            'retention_days' => (int)Num::max(0, (int)($data['retention_days'] ?? 30)),
            'evidence' => Arr::make($data['evidence'] ?? [])->toArray(),
            'timestamp' => $data['timestamp'] ?? null,
        ];
    }

    public static function context(array $data): array
    {
        return [
            'subject_ref' => (string)($data['subject_ref'] ?? $data['subject'] ?? ''),
            'session_ref' => (string)($data['session_ref'] ?? $data['session'] ?? ''),
            'action' => (string)($data['action'] ?? ''),
            'environment' => Arr::make($data['environment'] ?? [])->toArray(),
            'metadata' => Arr::make($data['metadata'] ?? [])->toArray(),
            'privacy' => Arr::make($data['privacy'] ?? [])->toArray(),
        ];
    }

    public static function result(float $score, array $signals = [], array $context = [], array $thresholds = []): array
    {
        $score = self::pressure($score);
        $thresholds = self::thresholds($thresholds);
        $decision = self::decisionFor($score, $thresholds);

        return [
            'score' => $score,
            'decision' => $decision,
            'challenge' => $decision === self::DECISION_CHALLENGE,
            'restrict' => Arr::make([self::DECISION_RESTRICT, self::DECISION_DENY])->contains($decision, true),
            'thresholds' => $thresholds,
            'signals' => Arr::make($signals)->toArray(),
            'context' => self::context($context),
            'reasons' => self::reasons($signals),
        ];
    }

    public static function thresholds(array $overrides = []): array
    {
        return [
            self::DECISION_CHALLENGE => self::pressure($overrides[self::DECISION_CHALLENGE] ?? 0.35),
            self::DECISION_RESTRICT => self::pressure($overrides[self::DECISION_RESTRICT] ?? 0.65),
            self::DECISION_DENY => self::pressure($overrides[self::DECISION_DENY] ?? 0.9),
        ];
    }

    public static function decisionFor(float $score, array $thresholds = []): string
    {
        $score = self::pressure($score);
        $thresholds = self::thresholds($thresholds);

        if ($score >= $thresholds[self::DECISION_DENY]) {
            return self::DECISION_DENY;
        }

        if ($score >= $thresholds[self::DECISION_RESTRICT]) {
            return self::DECISION_RESTRICT;
        }

        if ($score >= $thresholds[self::DECISION_CHALLENGE]) {
            return self::DECISION_CHALLENGE;
        }

        return self::DECISION_ALLOW;
    }

    public static function privacyGuidance(): array
    {
        return [
            'minimize_raw_identifiers' => 'Store references or hashes instead of raw credentials, device secrets, or full IP payloads.',
            'separate_sensitive_evidence' => 'Keep sensitive evidence behind the host security boundary and put only references in the contract.',
            'scope_by_action' => 'Evaluate signals against the requested action instead of treating every request as the same risk.',
        ];
    }

    public static function retentionRules(): array
    {
        return [
            self::SENSITIVITY_PUBLIC => ['default_days' => 90, 'max_days' => 365],
            self::SENSITIVITY_INTERNAL => ['default_days' => 30, 'max_days' => 180],
            self::SENSITIVITY_SENSITIVE => ['default_days' => 7, 'max_days' => 30],
        ];
    }

    public static function challengeMap(): array
    {
        return [
            self::DECISION_ALLOW => 'Continue without additional friction.',
            self::DECISION_CHALLENGE => 'Request step-up verification or additional evidence.',
            self::DECISION_RESTRICT => 'Limit the requested action or require stronger review.',
            self::DECISION_DENY => 'Deny the requested action and preserve audit evidence.',
        ];
    }

    protected static function pressure(mixed $value): float
    {
        if (!Num::is($value)) {
            return 0.0;
        }

        return Num::min(1.0, Num::max(0.0, (float)$value));
    }

    protected static function sensitivity(mixed $value): string
    {
        $value = Str::lower(Str::trim((string)$value));

        return Arr::make([
            self::SENSITIVITY_PUBLIC,
            self::SENSITIVITY_INTERNAL,
            self::SENSITIVITY_SENSITIVE,
        ])->contains($value, true) ? $value : self::SENSITIVITY_INTERNAL;
    }

    protected static function reasons(array $signals): array
    {
        $reasons = [];

        foreach ($signals as $signal) {
            $signal = self::signal(Arr::make($signal)->toArray());
            if ($signal['score'] <= 0.0) {
                continue;
            }

            $reasons[] = [
                'signal_id' => $signal['id'],
                'type' => $signal['type'],
                'score' => $signal['score'],
                'confidence' => $signal['confidence'],
            ];
        }

        return $reasons;
    }
}
