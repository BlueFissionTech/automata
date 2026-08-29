<?php

namespace BlueFission\Automata\LLM\Generation;

use BlueFission\Arr;

final class GenerationStatus
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const PARTIAL = 'partial';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const TIMED_OUT = 'timed_out';

    public static function all(): array
    {
        return [
            self::QUEUED,
            self::RUNNING,
            self::COMPLETED,
            self::PARTIAL,
            self::FAILED,
            self::CANCELLED,
            self::TIMED_OUT,
        ];
    }

    public static function terminal(): array
    {
        return [
            self::COMPLETED,
            self::PARTIAL,
            self::FAILED,
            self::CANCELLED,
            self::TIMED_OUT,
        ];
    }

    public static function isKnown(string $status): bool
    {
        return Arr::has(self::all(), $status, true);
    }

    public static function isTerminal(string $status): bool
    {
        return Arr::has(self::terminal(), $status, true);
    }
}
