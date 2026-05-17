<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

interface IMemoryEventStore
{
    public function append(MemoryEvent $event): void;
    public function events(?string $sessionId = null): array;
    public function clear(?string $sessionId = null): void;
}
