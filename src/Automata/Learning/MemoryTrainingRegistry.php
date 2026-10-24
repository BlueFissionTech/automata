<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;

/** Per-instance, bounded in-memory collection. No durable storage is implied. */
final class MemoryTrainingRegistry implements TrainingRegistry
{
    private Arr $records;

    public function __construct(private readonly int $limitPerOperation = 1000)
    {
        if ($limitPerOperation < 1) {
            throw new \InvalidArgumentException('The observation limit must be positive.');
        }
        $this->records = new Arr();
    }

    public function record(string $class, string $operation, TrainingObservation $observation): void
    {
        $key = $class . '::' . $operation;
        $rows = $this->records[$key] ?? [];
        $rows[] = $observation;
        if (Arr::size($rows) > $this->limitPerOperation) {
            array_shift($rows);
        }
        $this->records[$key] = $rows;
    }

    public function observations(string $class, string $operation): array
    {
        return $this->records[$class . '::' . $operation] ?? [];
    }
}
