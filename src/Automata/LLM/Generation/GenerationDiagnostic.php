<?php

namespace BlueFission\Automata\LLM\Generation;

use BlueFission\Arr;

class GenerationDiagnostic extends GenerationValue
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

    public function retryable(): bool
    {
        return (bool)$this->field('retryable');
    }

    public function isError(): bool
    {
        return $this->severity() === self::ERROR;
    }

    public function evidence(): array
    {
        return Arr::make($this->field('evidence') ?? [])->toArray();
    }

    protected function defaults(): array
    {
        return [
            'code' => '',
            'message' => '',
            'severity' => self::ERROR,
            'retryable' => false,
            'step_id' => null,
            'details' => [],
            'evidence' => [],
            'metadata' => [],
        ];
    }
}
