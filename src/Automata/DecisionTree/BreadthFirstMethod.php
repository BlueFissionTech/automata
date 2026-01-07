<?php

namespace BlueFission\Automata\DecisionTree;

use BlueFission\Arr;

/**
 * BreadthFirstMethod
 *
 * Traverses the decision tree in breadth-first order while tracking
 * the node with the best evaluation score.
 */
class BreadthFirstMethod extends BaseMethod
{
    public function traverse(INode $root): array
    {
        $queue = new Arr([$root]);
        $bestNode = $root;
        $bestScore = $root->evaluate();

        while ($queue->isNotEmpty()) {
            $currentNode = $queue->_shift();
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
                $queue->push($child);
            }
        }

        $this->decisionSelected($bestNode);

        return $bestNode->getValue();
    }
}

