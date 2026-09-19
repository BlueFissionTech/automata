<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;

/** Process-local reference store. Hosts own durable storage and concurrency policy. */
final class InMemoryExperienceStore implements IExperienceStore
{
    private array $records = [];

    public function save(Experience $experience): void
    {
        $this->records[$experience->id()] = $experience;
        Dev::do('automata.experience.stored', ['experience' => $experience]);
    }

    public function get(string $id): ?Experience { return $this->records[$id] ?? null; }

    public function experiences(): iterable
    {
        yield from Arr::values($this->records);
    }
}
