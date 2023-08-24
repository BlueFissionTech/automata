<?php
namespace BlueFission\Automata\GraphTheory;

class Node {
    protected $name;
    protected $edges = [];

    public function __construct($name, $edges) {
        $this->name = $name;
        $this->edges = $edges;
    }

    public function getName() {
        return $this->name;
    }

    public function getEdges() {
        return $this->edges;
    }

    public function getEdgeAttributes($nodeName) {
        if(isset($this->edges[$nodeName])) {
            return $this->edges[$nodeName];
        }
        return null;
    }
}