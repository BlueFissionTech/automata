<?php
namespace BlueFission\Automata\DecisionTree;

class Node implements INode {
    private $_value;
    private $_children = [];
    private $_evaluationFunction;

    public function __construct(array $value, callable $evaluationFunction) {
        $this->_value = $value;
        $this->_evaluationFunction = $evaluationFunction;
    }

    public function getValue(): array {
        return $this->_value;
    }

    public function getChildren(): array {
        return $this->_children;
    }

    public function addChild(INode $child): void {
        $this->_children[] = $child;
    }

    public function evaluate(): int {
        $function = $this->_evaluationFunction;
        return $function($this->_value, $this->_children);
    }
}