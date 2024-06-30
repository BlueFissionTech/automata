<?php
namespace BlueFission\Automata\DecisionTree;

class DepthFirstMethod implements IMethod {
    public function traverse(INode $root): array {
        $stack = [$root];
        $bestNode = $root;
        $bestScore = $root->evaluate();

        while (!empty($stack)) {
            $currentNode = array_pop($stack);
            $score = $currentNode->evaluate();
            if ($score > $bestScore) {
                $bestNode = $currentNode;
                $bestScore = $score;
            }

            foreach ($currentNode->getChildren() as $child) {
                array_push($stack, $child);
            }
        }

        return $bestNode->getValue();
    }
}
