<?php

namespace BlueFission\Automata\Support;

use BlueFission\Arr;
use BlueFission\Func;
use BlueFission\Num;
use BlueFission\Str;

trait Evaluates
{
    protected function asFunc(Func|callable $function): Func
    {
        return $function instanceof Func ? $function : new Func($function);
    }

    protected function invokeFunc(Func|callable $function, array $arguments): mixed
    {
        $macro = $this->asFunc($function);
        $accepted = count($macro->expects());

        if ($accepted <= 0) {
            return $macro->call();
        }

        return $macro->call(...Arr::slice($arguments, 0, $accepted));
    }

    protected function numericValue(mixed $value, float|int $default = 0): float|int
    {
        if (!Num::isValid($value)) {
            return $default;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $value = Str::trim((string)$value);

        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int)$value;
        }

        return (float)$value;
    }
}
