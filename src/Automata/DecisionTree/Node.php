<?php
namespace BlueFission\Automata\DecisionTree;

class Node implements INode {
    private $value;
    private $children = [];
    private $evaluationFunction;

    public function __construct(array $value, callable $evaluationFunction) {
        $this->value = $value;
        $this->evaluationFunction = $evaluationFunction;
    }

    public function getValue(): array {
        return $this->value;
    }

    public function getChildren(): array {
        return $this->children;
    }

    public function addChild(INode $child): void {
        $this->children[] = $child;
    }

    public function evaluate(): int {
        $function = $this->evaluationFunction;
        return $function($this->value, $this->children);
    }
}