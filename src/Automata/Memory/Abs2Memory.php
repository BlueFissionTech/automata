<?php

namespace BlueFission\Automata\Memory;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\GraphTheory\Graph;
use BlueFission\Automata\Context;

class Abs2Memory extends Graph implements IWorkingMemory {
    protected OrganizedCollection $_nodes;

    public function __construct(array $graph = []) {
        parent::__construct($graph);
        $this->_nodes = new OrganizedCollection();
    }

    public function store(string $label, Context $context, array $edges = []): void {
        $node = new MemoryNode($label, $edges, $context);
        $this->store($node);
    }

    public function store(MemoryNode $node): void {
        $this->_nodes->add($node, $node->getName());
        $this->_graph->set($node->getName(), $node->getEdges());
    }

    public function getMemory(string $label): ?MemoryNode {
        return $this->_nodes->has($label) ? $this->_nodes->get($label) : null;
    }

    public function associate(string $name1, string $name2, $weight = 1): void {
        $node1 = $this->_nodes->get($name1);
        $node2 = $this->_nodes->get($name2);

        if ($node1 && $node2) {
            $edges1 = $node1->getEdges();
            $edges2 = $node2->getEdges();

            $edges1[$name2] = $weight;
            $edges2[$name1] = $weight;

            $node1 = new MemoryNode($name1, $edges1, $node1->getContext());
            $node2 = new MemoryNode($name2, $edges2, $node2->getContext());

            $this->store($node1);
            $this->store($node2);
        }
    }

    public function reinforcePath(string $start, string $end): array {
        $path = $this->shortestPath($start, $end, fn($v) => 1); // basic weight
        foreach ($path as $nodeName) {
            /** @var MemoryNode $node */
            $node = $this->getMemory($nodeName);
            if ($node) {
                $node->reinforce();
            }
        }
        return $path;
    }

    public function contextSwitchPath(string $from, string $to): array {
        return $this->reinforcePath($from, $to);
    }

    public function recall(string $label): ?Context {
        $node = $this->getMemory($label);
        return $node?->context();
    }

    public function recallWithAssociations(string $label, int $max = 10): array {
        $node = $this->getMemory($label);
        if (!$node) return [];

        $edges = $node->getEdges();
        $related = [];

        foreach ($edges as $adj => $weight) {
            if (count($related) >= $max) break;
            $related[$adj] = $this->getMemory($adj)?->context();
        }

        return array_filter($related);
    }

    public function recallSimilar(Context $context, float $threshold = 0.5): array {
        $results = [];
        foreach ($this->_nodes as $label => $node) {
            if ($node instanceof MemoryNode) {
                $similarity = $node->similarity($context);
                if ($similarity >= $threshold) {
                    $results[$label] = [
                        'context' => $node->context(),
                        'similarity' => $similarity,
                    ];
                }
            }
        }

        uasort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        return $results;
    }

    public function shortestAssociation(string $start, string $end): array {
        return $this->shortestPath($start, $end, function($val) {
            return $val; // weight is already value
        });
    }

    public function forget(string $name): void {
        $this->_nodes->remove($name);
        $this->_graph->remove($name);
    }

    public function contents(): array {
        return $this->_nodes->contents();
    }
}
