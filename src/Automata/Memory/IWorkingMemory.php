<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Context;

interface IWorkingMemory
{
    /**
     * Store a new memory with a label, contextual payload, and optional edge weights.
     */
    public function addMemory(string $label, Context $context, array $edges = []): void;

    /**
     * Retrieve the underlying memory node for a label, if any.
     */
    public function getMemory(string $label): ?MemoryNode;

    /**
     * Reinforce all memories visited along a path between two labels.
     *
     * @return string[] ordered list of labels along the reinforced path
     */
    public function reinforcePath(string $start, string $end): array;

    /**
     * Convenience alias for a context switch between two labels.
     *
     * @return string[] ordered list of labels along the reinforced path
     */
    public function contextSwitchPath(string $from, string $to): array;

    /**
     * Recall the contextual payload for a specific label.
     */
    public function recall(string $label): ?Context;

    /**
     * Recall the contexts for directly associated memories.
     *
     * @return array<string,Context>
     */
    public function recallWithAssociations(string $label, int $max = 10): array;

    /**
     * Associate two memories with a weighted edge.
     */
    public function associate(string $name1, string $name2, float $weight = 1.0): void;

    /**
     * Compute the lightest-weight association path between two labels.
     *
     * @return string[] ordered list of labels, or [] if no path
     */
    public function shortestAssociation(string $start, string $end): array;

    /**
     * Remove a memory and its associations from working memory.
     */
    public function forget(string $name): void;

    /**
     * Retrieve all stored memories keyed by label.
     *
     * @return array<string,MemoryNode>
     */
    public function contents(): array;
}
