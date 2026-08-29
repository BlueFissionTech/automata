<?php

namespace BlueFission\Automata\Language\Claims;

final class ClaimNormalizationStatus
{
    public const NORMALIZED = 'normalized';
    public const UNSUPPORTED = 'unsupported';
    public const AMBIGUOUS = 'ambiguous';
    public const MALFORMED = 'malformed';
    public const POLICY_REJECTED = 'policy_rejected';

    public static function all(): array
    {
        return [
            self::NORMALIZED,
            self::UNSUPPORTED,
            self::AMBIGUOUS,
            self::MALFORMED,
            self::POLICY_REJECTED,
        ];
    }
}
