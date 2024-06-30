<?php
namespace BlueFission\Automata\GraphTheory;

class Graph {
    protected $graph = [];
    protected $nodes = [];

    public function __construct($graph) {
        if ( is_array($graph) ) {
            $this->graph = $graph;
        }
    }

    public function addNode(Node $node) {
        foreach($node->getEdges() as $edge => $val) {
            if(!isset($this->nodes[$edge])) {
                $this->nodes[$edge] = true;
            }
        }
        $this->nodes[$node->getName()] = $node;
        $this->graph[$node->getName()] = $node->getEdges();
    }

    public function shortestPath($start, $end, $fitnessFunction) {
        $distances = [];
        $previous = [];
        $nodes = $this->nodes;

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

            foreach($this->graph[$closest] as $neighbor => $value) {
                if(isset($nodes[$neighbor])) {
                    $alt = $distances[$closest] + $fitnessFunction($value);

                    if($alt < $distances[$neighbor]) {
                        $distances[$neighbor] = $alt;
                        $previous[$neighbor] = $closest;
                    }
                }
            }

            unset($nodes[$closest]);
            asort($distances);
        }

        return [];
    }
}
