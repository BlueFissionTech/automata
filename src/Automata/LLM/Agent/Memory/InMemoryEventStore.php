<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

class InMemoryEventStore implements IMemoryEventStore
{
    protected array $events = [];

    public function append(MemoryEvent $event): void
    {
        $this->events[] = $event->toArray();
    }

    public function events(?string $sessionId = null): array
    {
        if ($sessionId === null) {
            return $this->events;
        }

        return array_values(array_filter(
            $this->events,
            fn (array $event): bool => ($event['session_id'] ?? null) === $sessionId
        ));
    }

    public function clear(?string $sessionId = null): void
    {
        if ($sessionId === null) {
            $this->events = [];
            return;
        }

        $this->events = array_values(array_filter(
            $this->events,
            fn (array $event): bool => ($event['session_id'] ?? null) !== $sessionId
        ));
    }
}
