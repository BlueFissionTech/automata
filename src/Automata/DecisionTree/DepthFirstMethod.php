<?php
namespace BlueFission\Automata\DecisionTree;

use BlueFission\Arr;

/**
 * DepthFirstMethod
 *
 * Traverses the decision tree in depth-first order while tracking
 * the node with the best evaluation score encountered so far.
 *
 * This is a good default when:
 * - the evaluation function is cheap to compute, and
 * - the tree is not so deep that stack-based traversal is prohibitive.
 */
class DepthFirstMethod extends BaseMethod {
    public function traverse(INode $root): array {
        // Use Arr as a LIFO stack for depth-first traversal.
        $stack = new Arr([$root]);
        $bestNode = $root;
        $bestScore = $root->evaluate();

        while ($stack->isNotEmpty()) {
            // Pop the last-pushed node to explore as deep as possible.
            $currentNode = $stack->_pop();
            if (!$currentNode) {
                continue;
            }

            $this->visitNode($currentNode);

            $score = $currentNode->evaluate();
            if ($score > $bestScore) {
                $bestNode = $currentNode;
                $bestScore = $score;
            }

            foreach ($currentNode->getChildren() as $child) {
                $stack->push($child);
            }
        }

        $this->decisionSelected($bestNode);

        return $bestNode->getValue();
    }
}
