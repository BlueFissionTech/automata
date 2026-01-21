<?php

namespace Examples\DisasterResponse\Sim;

class Cell
{
    private string $type;
    private bool $damaged;
    private bool $blocked;
    private int $people;
    private int $supplies;

    public function __construct(string $type, array $state = [])
    {
        $this->type = $type;
        $this->damaged = (bool)($state['damaged'] ?? false);
        $this->blocked = (bool)($state['blocked'] ?? false);
        $this->people = (int)($state['people'] ?? 0);
        $this->supplies = (int)($state['supplies'] ?? 0);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function isDamaged(): bool
    {
        return $this->damaged;
    }

    public function people(): int
    {
        return $this->people;
    }

    public function supplies(): int
    {
        return $this->supplies;
    }

    public function clear(): bool
    {
        if (!$this->blocked) {
            return false;
        }
        $this->blocked = false;
        return true;
    }

    public function repair(): bool
    {
        if (!$this->damaged) {
            return false;
        }
        $this->damaged = false;
        return true;
    }

    public function rescue(): bool
    {
        if ($this->people <= 0) {
            return false;
        }
        $this->people--;
        return true;
    }

    public function deliver(): bool
    {
        if ($this->supplies <= 0) {
            return false;
        }
        $this->supplies--;
        return true;
    }

    public function tags(): array
    {
        $tags = [$this->type];

        if ($this->blocked) {
            $tags[] = 'blocked';
        }
        if ($this->damaged) {
            $tags[] = 'damage';
        }
        if ($this->people > 0) {
            $tags[] = 'people';
        }
        if ($this->supplies > 0) {
            $tags[] = 'supplies';
        }

        return $tags;
    }
}
