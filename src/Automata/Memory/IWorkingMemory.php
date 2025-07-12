<?php

namespace BlueFission\Automata\Memory;

interface IWorkingMemory {
    public function addMemory(string $label, Context $context, array $edges = []): void;
    public function getMemory(string $label): ?MemoryNode;
    public function reinforcePath(string $start, string $end): array;
    public function contextSwitchPath(string $from, string $to): array;
    public function recall(string $label): ?Context;
    public function recallWithAssociations(string $label, int $max = 10): array;
    
    public function associate(string $name1, string $name2, $weight = 1): void;
    public function shortestAssociation(string $start, string $end): array;
    public function forget(string $name): void;
    public function contents(): array;
}