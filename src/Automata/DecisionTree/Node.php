<?php
namespace BlueFission\Automata\DecisionTree;

use BlueFission\Automata\Support\Evaluates;
use BlueFission\Func;

class Node implements INode {
    use Evaluates;

    private $_value;
    private $_children = [];
    private Func $_evaluationFunction;

    public function __construct(array $value, Func|callable $evaluationFunction) {
        $this->_value = $value;
        $this->_evaluationFunction = $this->asFunc($evaluationFunction);
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

    public function evaluate(array $state = [], Func|callable|null $assessor = null): int|float {
        if ($assessor) {
            $assessed = $this->invokeFunc($assessor, [$this->_value, $this->_children, $state, $this]);
            if ($assessed !== null) {
                return $this->numericValue($assessed);
            }
        }

        $score = $this->invokeFunc($this->_evaluationFunction, [$this->_value, $this->_children, $state, $this]);

        return $this->numericValue($score);
    }
}
