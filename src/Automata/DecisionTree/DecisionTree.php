<?php
namespace BlueFission\Automata\DecisionTree;

use BlueFission\DevElation as Dev;

class DecisionTree implements IDecisionTree {
    private $_root;

    public function getRoot(): INode {
        return $this->_root;
    }

    public function setRoot(INode $node): void {
        $this->_root = $node;
    }

    public function decide(IMethod $method): array {
        // Allow hooks around decision-tree execution.
        Dev::do('automata.decisiontree.decisiontree.decide.action1', ['root' => $this->_root, 'method' => $method]);
        $result = $method->traverse($this->_root);
        $result = Dev::apply('automata.decisiontree.decisiontree.decide.1', $result);

        return $result;
    }
}
