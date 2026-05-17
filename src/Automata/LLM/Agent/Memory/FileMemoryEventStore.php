<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

use BlueFission\Arr;

class FileMemoryEventStore implements IMemoryEventStore
{
    protected string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    public function append(MemoryEvent $event): void
    {
        file_put_contents($this->path, json_encode($event->toArray()) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function events(?string $sessionId = null): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $events = [];
        foreach (file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $event = json_decode($line, true);
            if (!Arr::is($event)) {
                continue;
            }
            if ($sessionId !== null && ($event['session_id'] ?? null) !== $sessionId) {
                continue;
            }
            $events[] = $event;
        }

        return $events;
    }

    public function clear(?string $sessionId = null): void
    {
        if ($sessionId === null) {
            file_put_contents($this->path, '');
            return;
        }

        $remaining = array_filter(
            $this->events(),
            fn (array $event): bool => ($event['session_id'] ?? null) !== $sessionId
        );

        file_put_contents(
            $this->path,
            implode(PHP_EOL, array_map(fn (array $event): string => json_encode($event), $remaining)) . (count($remaining) ? PHP_EOL : '')
        );
    }
}
