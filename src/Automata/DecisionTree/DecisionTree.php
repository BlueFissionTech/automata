<?php
namespace BlueFission\Automata\DecisionTree;

class DecisionTree implements IDecisionTree {
    private $_root;

    public function getRoot(): INode {
        return $this->_root;
    }

    public function setRoot(INode $node): void {
        $this->_root = $node;
    }

    public function decide(IMethod $method): array {
        return $method->traverse($this->_root);
    }
}