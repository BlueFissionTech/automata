<?php

namespace BlueFission\Automata\Learning;

use InvalidArgumentException;

/** @internal Copies persisted data without retaining runtime objects or references. */
final class RecordSnapshot
{
    public static function copy(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 32) {
            throw new InvalidArgumentException('Record exceeds the maximum snapshot depth.');
        }
        if (is_array($value)) {
            $copy = [];
            foreach ($value as $key => $item) {
                $copy[$key] = self::copy($item, $depth + 1);
            }
            return $copy;
        }
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)
            || (is_float($value) && is_finite($value))) {
            return $value;
        }
        throw new InvalidArgumentException('Records accept only finite, serializable scalar and array values.');
    }

    public static function identifier(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException($field . ' must be a nonempty string.');
        }
        return $value;
    }
}
