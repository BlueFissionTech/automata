<?php

namespace BlueFission\Automata\Language\Claims;

final class ClaimSourceForm
{
    public const RAW = 'raw';
    public const WRAPPED = 'wrapped';
    public const TEXT = 'text';

    public static function all(): array
    {
        return [self::RAW, self::WRAPPED, self::TEXT];
    }

    public static function isKnown(string $form): bool
    {
        return in_array($form, self::all(), true);
    }
}
