<?php

namespace BlueFission\Automata\Language\Claims;

class ClaimDiagnostic extends ClaimValue
{
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    public function code(): string
    {
        return (string)$this->field('code');
    }

    public function severity(): string
    {
        return (string)$this->field('severity');
    }

    protected function defaults(): array
    {
        return [
            'code' => '',
            'message' => '',
            'severity' => self::ERROR,
            'details' => [],
            'evidence' => [],
            'metadata' => [],
        ];
    }
}
