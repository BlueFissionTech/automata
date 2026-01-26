<?php

namespace BlueFission\Automata\Classification;

use BlueFission\Automata\Path\Graph as BaseGraph;
use BlueFission\Automata\Path\Node;
use BlueFission\DevElation as Dev;

class Graph extends BaseGraph
{
    public function addTag(string $label): void
    {
        $label = Dev::apply('classification.tag_graph.add_tag', $label);
        if (!$this->node($label)) {
            $this->addNode(new Node($label));
        }
        Dev::do('classification.tag_graph.tag_added', ['label' => $label]);
    }

    public function relate(string $from, string $to, float $weight = 1.0): void
    {
        $from = Dev::apply('classification.tag_graph.relate_from', $from);
        $to = Dev::apply('classification.tag_graph.relate_to', $to);

        $this->connect($from, $to, ['weight' => $weight], false);

        Dev::do('classification.tag_graph.related', [
            'from' => $from,
            'to' => $to,
            'weight' => $weight,
        ]);
    }
}
