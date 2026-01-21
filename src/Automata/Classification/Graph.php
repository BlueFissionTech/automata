<?php

namespace BlueFission\Automata\Classification;

use BlueFission\Automata\GraphTheory\Graph as BaseGraph;
use BlueFission\Automata\GraphTheory\Node;
use BlueFission\DevElation as Dev;

class Graph extends BaseGraph
{
    public function addTag(string $label): void
    {
        $label = Dev::apply('classification.tag_graph.add_tag', $label);
        $node = new Node($label, []);
        $this->addNode($node);
        Dev::do('classification.tag_graph.tag_added', ['label' => $label]);
    }

    public function relate(string $from, string $to, float $weight = 1.0): void
    {
        $from = Dev::apply('classification.tag_graph.relate_from', $from);
        $to = Dev::apply('classification.tag_graph.relate_to', $to);

        $fromNode = $this->_nodes[$from] ?? null;
        $toNode = $this->_nodes[$to] ?? null;

        if (!$fromNode instanceof Node) {
            $fromNode = new Node($from, []);
        }
        if (!$toNode instanceof Node) {
            $toNode = new Node($to, []);
        }

        $fromEdges = $fromNode->getEdges();
        $toEdges = $toNode->getEdges();

        $fromEdges[$to] = ['weight' => $weight];
        $toEdges[$from] = ['weight' => $weight];

        $this->addNode(new Node($from, $fromEdges));
        $this->addNode(new Node($to, $toEdges));

        Dev::do('classification.tag_graph.related', [
            'from' => $from,
            'to' => $to,
            'weight' => $weight,
        ]);
    }
}
