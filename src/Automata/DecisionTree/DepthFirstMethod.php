<?php
namespace BlueFission\Automata\DecisionTree;

use BlueFission\Arr;

class DepthFirstMethod implements IMethod {
    public function traverse(INode $root): array {
        $stack = new Arr([$root]);
        $bestNode = $root;
        $bestScore = $root->evaluate();

        while ($stack->isNotEmpty()) {
            $currentNode = $stack->pop();
            $score = $currentNode->evaluate();
            if ($score > $bestScore) {
                $bestNode = $currentNode;
                $bestScore = $score;
            }

            foreach ($currentNode->getChildren() as $child) {
                $stack->push($child);
            }
        }

        return $bestNode->getValue();
    }
}
