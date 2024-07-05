<?php
namespace BlueFission\Automata\GraphTheory;

class Graph {
    protected $_graph;
    protected $_nodes;

    public function __construct(array $graph = []) {
        $this->_graph = new Arr($graph);
        $this->_nodes = new Arr([]);
    }

    public function addNode(Node $node) {
        foreach($node->getEdges() as $edge => $val) {
            if(!$this->_nodes->hasKey($edge)) {
                $this->_nodes->set($edge, true);
            }
        }
        $this->_nodes->set($node->getName(), $node);
        $this->_graph->set($node->getName(), $node->getEdges());
    }

    public function shortestPath($start, $end, $fitnessFunction) {
        $distances = [];
        $previous = [];
        $nodes = $this->_nodes;

        foreach($nodes as $node) {
            $distances[$node->getName()] = PHP_INT_MAX;
            $previous[$node->getName()] = null;
        }

        $distances[$start] = 0;
        asort($distances);

        while(!empty($nodes)) {
            $closest = key($distances);

            if($closest === $end) {
                $path = [];

                while($previous[$closest]) {
                    $path[] = $closest;
                    $closest = $previous[$closest];
                }

                $path[] = $start;

                return array_reverse($path);
            }

            if($distances[$closest] === PHP_INT_MAX) {
                break;
            }

            foreach($this->_graph[$closest] as $neighbor => $value) {
                if($nodes->hasKey($neighbor)) {
                    $alt = $distances[$closest] + $fitnessFunction($value);

                    if($alt < $distances[$neighbor]) {
                        $distances[$neighbor] = $alt;
                        $previous[$neighbor] = $closest;
                    }
                }
            }

            $nodes->delete($closest)
            asort($distances);
        }

        return [];
    }
}
