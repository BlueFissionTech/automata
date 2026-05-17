<?php

namespace BlueFission\Automata\LLM\Agent\Security;

class LpciFinding
{
    public const ALLOWED = 'allowed';
    public const WARNING = 'warning';
    public const BLOCKED = 'blocked';
    public const UNKNOWN = 'unknown';

    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = array_replace_recursive([
            'status' => self::ALLOWED,
            'stage' => null,
            'category' => null,
            'message' => '',
            'evidence' => [],
        ], $data);
    }

    public function status(): string
    {
        return (string)$this->data['status'];
    }

    public function blocked(): bool
    {
        return $this->status() === self::BLOCKED;
    }

    public function warning(): bool
    {
        return $this->status() === self::WARNING;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
