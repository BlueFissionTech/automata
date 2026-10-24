<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Num;
use BlueFission\Str;

/** Keeps automatic observation away from objects, resources, and unbounded data. */
final class TrainingValue
{
    public static function supported(mixed $value, int $depth = 0): bool
    {
        if ($depth > 8) {
            return false;
        }
        if (Num::isFloat($value)) {
            return is_finite($value);
        }
        if (Str::is($value)) {
            return Str::len($value) <= 4096;
        }
        if (Num::isInt($value) || Flag::isBool($value) || $value === null) {
            return true;
        }
        if (!Arr::is($value) || Arr::size($value) > 100) {
            return false;
        }
        foreach ($value as $item) {
            if (!self::supported($item, $depth + 1)) {
                return false;
            }
        }
        return true;
    }

    public static function key(array $input, array $context): string
    {
        return hash('sha256', serialize([$input, $context]));
    }
}
