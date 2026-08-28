<?php

namespace BlueFission\Automata\LLM\Agent\Delegation;

final class DelegationStatus
{
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const PARTIAL = 'partial';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const TIMED_OUT = 'timed_out';

    public static function terminal(): array
    {
        return [
            self::REJECTED,
            self::COMPLETED,
            self::PARTIAL,
            self::FAILED,
            self::CANCELLED,
            self::TIMED_OUT,
        ];
    }
}
