<?php

namespace BlueFission\Automata\LLM\Agent\Security;

use BlueFission\Arr;

class LpciFinding
{
    public const ALLOWED = 'allowed';
    public const WARNING = 'warning';
    public const BLOCKED = 'blocked';
    public const UNKNOWN = 'unknown';

    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = $this->merge([
            'status' => self::ALLOWED,
            'stage' => null,
            'category' => null,
            'message' => '',
            'evidence' => [],
        ], $data);
    }

    /**
     * Return the finding status.
     */
    public function status(): string
    {
        return (string)$this->data['status'];
    }

    /**
     * Determine whether this finding should block the surface.
     */
    public function blocked(): bool
    {
        return $this->status() === self::BLOCKED;
    }

    /**
     * Determine whether this finding should warn without full blocking.
     */
    public function warning(): bool
    {
        return $this->status() === self::WARNING;
    }

    /**
     * Export the finding for audit trails.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Merge finding defaults without raw recursive array replacement.
     */
    protected function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (Arr::hasKey($base, $key) && Arr::is($base[$key]) && Arr::is($value)) {
                $base[$key] = $this->merge($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
