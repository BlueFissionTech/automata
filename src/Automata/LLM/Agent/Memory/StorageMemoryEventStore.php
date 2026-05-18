<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Data\Storage\Storage;
use BlueFission\Net\HTTP;

class StorageMemoryEventStore implements IMemoryEventStore
{
    protected Storage $storage;

    /**
     * Create an event store backed by a DevElation storage adapter.
     */
    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
        $this->storage->activate();
    }

    /**
     * Append one lifecycle event to storage.
     */
    public function append(MemoryEvent $event): void
    {
        $events = $this->events();
        $events[] = $event->toArray();
        $this->write($events);
    }

    /**
     * Return stored events, optionally scoped to one session id.
     */
    public function events(?string $sessionId = null): array
    {
        $this->storage->read();
        $contents = $this->storage->contents();
        $events = Arr::is($contents) && Arr::hasKey($contents, 'events') ? Arr::make($contents['events'])->toArray() : [];

        if ($sessionId === null) {
            return $events;
        }

        return (new Collection($events))
            ->filter(fn (array $event): bool => ($event['session_id'] ?? null) === $sessionId)
            ->toArray();
    }

    /**
     * Clear all events or only one session's events.
     */
    public function clear(?string $sessionId = null): void
    {
        if ($sessionId === null) {
            $this->write([]);
            return;
        }

        $events = (new Collection($this->events()))
            ->filter(fn (array $event): bool => ($event['session_id'] ?? null) !== $sessionId)
            ->toArray();

        $this->write($events);
    }

    /**
     * Persist the event array through the configured storage adapter.
     */
    protected function write(array $events): void
    {
        $this->storage->contents(HTTP::jsonEncode(['events' => $events]));
        $this->storage->write();
    }
}
