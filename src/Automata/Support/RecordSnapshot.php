<?php

namespace BlueFission\Automata\Support;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Num;
use BlueFission\Ref;
use BlueFission\Str;
use BlueFission\Val;
use InvalidArgumentException;

/** @internal Detached, finite data shared by versioned library records. */
final class RecordSnapshot
{
    public static function copy(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 32) { throw new InvalidArgumentException('Record exceeds the maximum snapshot depth.'); }
        if (is_object($value) || Ref::is($value)) {
            throw new InvalidArgumentException('Records accept only finite, serializable scalar and array values.');
        }
        if (Arr::is($value)) {
            $copy = [];
            foreach ($value as $key => $item) { $copy[$key] = self::copy($item, $depth + 1); }
            return $copy;
        }
        if (Val::isNull($value) || Flag::isBool($value) || Num::isInt($value) || Str::is($value)
            || (Num::isFloat($value) && Num::check($value, 'is_finite'))) { return $value; }
        throw new InvalidArgumentException('Records accept only finite, serializable scalar and array values.');
    }

    public static function identifier(mixed $value, string $field): string
    {
        if (is_object($value) || !Str::is($value) || Str::make($value)->trim()->val() === '') {
            throw new InvalidArgumentException($field . ' must be a nonempty string.');
        }
        return $value;
    }

    public static function number(mixed $value, string $field, float $minimum = 0.0, ?float $maximum = null): float
    {
        if (is_object($value) || (!Num::isInt($value) && !Num::isFloat($value))
            || !Num::check($value, 'is_finite') || $value < $minimum || ($maximum !== null && $value > $maximum)) {
            throw new InvalidArgumentException($field . ' must be a finite number within its declared bounds.');
        }
        return (float) $value;
    }

    /** JSON object key order is immaterial; list order and scalar types remain significant. */
    public static function canonical(mixed $value): mixed
    {
        return self::ordered(self::copy($value));
    }

    /** The entire tree is validated and detached once before recursive ordering. */
    private static function ordered(mixed $value): mixed
    {
        if (!Arr::is($value)) { return $value; }
        $ordered = Arr::make($value)->map(self::ordered(...))->val();
        if (!Arr::check($ordered, 'array_is_list')) { ksort($ordered, SORT_STRING); }
        return $ordered;
    }

    /** Typed binary float encoding avoids PHP's configurable serialization precision. */
    public static function fingerprint(mixed $value): string
    {
        $hash = hash_init('sha256');
        $visit = function (mixed $item) use (&$visit, $hash): void {
            if (Arr::is($item)) {
                hash_update($hash, 'a' . Arr::count($item) . ':');
                foreach ($item as $key => $entry) { $visit($key); $visit($entry); }
            } elseif (Str::is($item)) {
                hash_update($hash, 's' . strlen($item) . ':' . $item);
            } elseif (Num::isFloat($item)) {
                hash_update($hash, 'd' . pack('E', $item));
            } elseif (Num::isInt($item)) {
                hash_update($hash, 'i' . $item . ';');
            } elseif (Flag::isBool($item)) {
                hash_update($hash, $item ? 't' : 'f');
            } else {
                hash_update($hash, 'n');
            }
        };
        $visit(self::canonical($value));
        return hash_final($hash);
    }
}
