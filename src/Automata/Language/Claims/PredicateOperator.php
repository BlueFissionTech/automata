<?php

namespace BlueFission\Automata\Language\Claims;

final class PredicateOperator
{
    public const EQUALS = 'equals';
    public const NOT_EQUALS = 'not_equals';
    public const GREATER_THAN = 'greater_than';
    public const GREATER_THAN_OR_EQUAL = 'greater_than_or_equal';
    public const LESS_THAN = 'less_than';
    public const LESS_THAN_OR_EQUAL = 'less_than_or_equal';
    public const IN = 'in';
    public const CONTAINS = 'contains';
    public const AND = 'and';
    public const OR = 'or';
    public const NOT = 'not';

    public static function all(): array
    {
        return [
            self::EQUALS,
            self::NOT_EQUALS,
            self::GREATER_THAN,
            self::GREATER_THAN_OR_EQUAL,
            self::LESS_THAN,
            self::LESS_THAN_OR_EQUAL,
            self::IN,
            self::CONTAINS,
            self::AND,
            self::OR,
            self::NOT,
        ];
    }

    public static function isKnown(string $operator): bool
    {
        return in_array($operator, self::all(), true);
    }

    public static function isLogical(string $operator): bool
    {
        return in_array($operator, [self::AND, self::OR, self::NOT], true);
    }
}
