<?php

namespace BlueFission\Automata\Learning;

/** Keeps automatic observation away from objects, resources, and unbounded data. */
final class TrainingValue
{
    public static function supported(mixed $value, int $depth = 0): bool
    {
        if ($depth > 8) {
            return false;
        }
        if (is_float($value)) {
            return is_finite($value);
        }
        if (is_string($value)) {
            return strlen($value) <= 4096;
        }
        if (is_int($value) || is_bool($value) || $value === null) {
            return true;
        }
        if (!is_array($value) || count($value) > 100) {
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
