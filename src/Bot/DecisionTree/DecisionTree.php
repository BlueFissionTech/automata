<?php
namespace BlueFission\Automata\DecisionTree;

class DecisionTree implements IDecisionTree {
    private $root;

    public function getRoot(): INode {
        return $this->root;
    }

    public function setRoot(INode $node): void {
        $this->root = $node;
    }

    public function decide(IMethod $method): array {
        return $method->traverse($this->root);
    }
}