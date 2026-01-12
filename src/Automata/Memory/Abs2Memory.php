<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\GraphTheory\Graph;
use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;

/**
 * ABS/working-memory implementation backed by a graph of MemoryNode instances.
 *
 * Nodes are stored both in a Develation Arr-backed Graph (for path operations)
 * and in an OrganizedCollection (for reinforcement, decay, and ranked retrieval).
 */
class Abs2Memory extends Graph implements IWorkingMemory
{
    /** @var OrganizedCollection<string,MemoryNode> */
    protected OrganizedCollection $_memoryNodes;

    public function __construct(array $graph = [])
    {
        parent::__construct($graph);
        $this->_memoryNodes = new OrganizedCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function addMemory(string $label, Context $context, array $edges = []): void
    {
        // Allow filters to adjust or enrich contexts before storage.
        $context = Dev::apply('automata.memory.abs2memory.addMemory.1', $context);
        Dev::do('automata.memory.abs2memory.addMemory.action1', ['label' => $label, 'context' => $context]);

        $node = new MemoryNode($label, $edges, $context);
        $this->storeNode($node);
    }

    /**
     * Internal helper to keep graph and memory collection in sync.
     */
    protected function storeNode(MemoryNode $node): void
    {
        $label = $node->getName();

        $this->_memoryNodes->add($node, $label);

        // Keep Graph::_nodes and Graph::_graph updated via the public API.
        $this->addNode($node);
    }

    public function getMemory(string $label): ?MemoryNode
    {
        return $this->_memoryNodes->has($label)
            ? $this->_memoryNodes->get($label)
            : null;
    }

    public function associate(string $name1, string $name2, float $weight = 1.0): void
    {
        $node1 = $this->getMemory($name1);
        $node2 = $this->getMemory($name2);

        if (!$node1 || !$node2) {
            return;
        }

        $edges1 = $node1->getEdges();
        $edges2 = $node2->getEdges();

        $edges1[$name2] = $weight;
        $edges2[$name1] = $weight;

        $this->storeNode(new MemoryNode($name1, $edges1, $node1->getContext()));
        $this->storeNode(new MemoryNode($name2, $edges2, $node2->getContext()));
    }

    public function reinforcePath(string $start, string $end): array
    {
        // Treat every traversed edge as equal cost here; callers that care
        // about weights should use shortestAssociation instead.
        $path = $this->shortestPath($start, $end, static fn($v) => 1);

        foreach ($path as $label) {
            $node = $this->getMemory($label);
            if ($node instanceof MemoryNode) {
                $node->reinforce();
                $this->storeNode($node);
            }
        }

        return $path;
    }

    public function contextSwitchPath(string $from, string $to): array
    {
        return $this->reinforcePath($from, $to);
    }

    public function recall(string $label): ?Context
    {
        $node = $this->getMemory($label);

        $context = $node?->getContext();

        // Let hooks observe or transform recalled contexts.
        $context = Dev::apply('automata.memory.abs2memory.recall.1', $context);
        Dev::do('automata.memory.abs2memory.recall.action1', ['label' => $label, 'context' => $context]);

        return $context;
    }

    public function recallWithAssociations(string $label, int $max = 10): array
    {
        $node = $this->getMemory($label);
        if (!$node) {
            return [];
        }

        $edges = $node->getEdges();
        $related = [];

        foreach ($edges as $adj => $_weight) {
            if (count($related) >= $max) {
                break;
            }

            $adjacent = $this->getMemory($adj);
            if ($adjacent instanceof MemoryNode) {
                $related[$adj] = $adjacent->getContext();
            }
        }

        return $related;
    }

    /**
     * Recall similar memories using MemoryNode's built-in similarity metric.
     *
     * @return array<string,array{context:Context,similarity:float}>
     */
    public function recallSimilar(Context $context, float $threshold = 0.5): array
    {
        // Filters may tweak the query context before similarity search.
        $context = Dev::apply('automata.memory.abs2memory.recallSimilar.1', $context);

        $results = [];
        $stored = $this->_memoryNodes->contents();

        foreach ($stored as $label => $entry) {
            $node = $entry['value'] ?? null;
            if (!$node instanceof MemoryNode) {
                continue;
            }

            $similarity = $node->similarity($context);

            if ($similarity >= $threshold) {
                $results[$label] = [
                    'context' => $node->getContext(),
                    'similarity' => $similarity,
                ];
            }
        }

        uasort($results, static fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Allow post-processing or logging of similarity results.
        $results = Dev::apply('automata.memory.abs2memory.recallSimilar.2', $results);
        Dev::do('automata.memory.abs2memory.recallSimilar.action1', ['query' => $context, 'results' => $results]);

        return $results;
    }

    public function shortestAssociation(string $start, string $end): array
    {
        // Use the edge weight directly as the path cost when present; if the
        // edge value is a structured array, fall back to a neutral cost.
        return $this->shortestPath(
            $start,
            $end,
            static function ($val) {
                if (is_numeric($val)) {
                    return (float)$val;
                }

                if (is_array($val) && isset($val['weight']) && is_numeric($val['weight'])) {
                    return (float)$val['weight'];
                }

                return 1.0;
            }
        );
    }

    public function forget(string $name): void
    {
        if ($this->_memoryNodes->has($name)) {
            $this->_memoryNodes->remove($name);
        }

        // Remove from the underlying graph and node registry, if present.
        if (isset($this->_graph[$name])) {
            $this->_graph->delete($name);
        }

        if (isset($this->_nodes[$name])) {
            $this->_nodes->delete($name);
        }
    }

    public function contents(): array
    {
        $out = [];
        foreach ($this->_memoryNodes->contents() as $label => $entry) {
            if (isset($entry['value']) && $entry['value'] instanceof MemoryNode) {
                $out[$label] = $entry['value'];
            }
        }

        return $out;
    }
}
