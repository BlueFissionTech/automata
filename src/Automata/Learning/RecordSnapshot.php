<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Num;
use BlueFission\Ref;
use BlueFission\Str;
use BlueFission\Val;
use InvalidArgumentException;

/** @internal Copies persisted data without retaining runtime objects or references. */
final class RecordSnapshot
{
    public static function copy(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 32) {
            throw new InvalidArgumentException('Record exceeds the maximum snapshot depth.');
        }
        // Primitive helpers unwrap IVal objects; reject runtime values before using them.
        if (is_object($value) || Ref::is($value)) {
            throw new InvalidArgumentException('Records accept only finite, serializable scalar and array values.');
        }
        if (Arr::is($value)) {
            // Mapping retains keys and order while recursively detaching every value.
            return Arr::make($value)
                ->map(static fn (mixed $item): mixed => self::copy($item, $depth + 1))
                ->val();
        }
        if (Val::isNull($value) || Flag::isBool($value) || Num::isInt($value) || Str::is($value)
            || (Num::isFloat($value) && Num::check($value, 'is_finite'))) {
            return $value;
        }
        throw new InvalidArgumentException('Records accept only finite, serializable scalar and array values.');
    }

    public static function identifier(mixed $value, string $field): string
    {
        if (is_object($value) || !Str::is($value) || Str::make($value)->trim()->val() === '') {
            throw new InvalidArgumentException($field . ' must be a nonempty string.');
        }
        return $value;
    }
}
