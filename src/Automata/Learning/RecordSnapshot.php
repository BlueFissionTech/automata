<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Automata\Support\RecordSnapshot as Snapshot;

/** @internal Compatibility seam for the existing learning records. */
final class RecordSnapshot
{
    public static function copy(mixed $value, int $depth = 0): mixed { return Snapshot::copy($value, $depth); }
    public static function identifier(mixed $value, string $field): string { return Snapshot::identifier($value, $field); }
}
