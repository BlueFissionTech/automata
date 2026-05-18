<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

use BlueFission\Collections\Collection;

class InMemoryEventStore implements IMemoryEventStore
{
    protected array $events = [];

    /**
     * Append one event to process-local storage.
     */
    public function append(MemoryEvent $event): void
    {
        $this->events[] = $event->toArray();
    }

    /**
     * Return events, optionally scoped to one session.
     */
    public function events(?string $sessionId = null): array
    {
        if ($sessionId === null) {
            return $this->events;
        }

        $events = (new Collection($this->events))
            ->filter(fn (array $event): bool => ($event['session_id'] ?? null) === $sessionId)
            ->toArray();

        $filtered = [];
        foreach ($events as $event) {
            $filtered[] = $event;
        }

        return $filtered;
    }

    /**
     * Clear all events or only one session's events.
     */
    public function clear(?string $sessionId = null): void
    {
        if ($sessionId === null) {
            $this->events = [];
            return;
        }

        $events = (new Collection($this->events))
            ->filter(fn (array $event): bool => ($event['session_id'] ?? null) !== $sessionId)
            ->toArray();

        $this->events = [];
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }
}
