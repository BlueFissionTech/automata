<?php

namespace BlueFission\Automata\DecisionTree;

use BlueFission\Arr;

/**
 * LeafOnlyBestMethod
 *
 * Traverses the tree depth-first but only considers leaf nodes when
 * selecting the best decision. Useful when internal nodes are
 * intermediate questions and only leaves represent actions.
 */
class LeafOnlyBestMethod extends BaseMethod
{
    public function traverse(INode $root): array
    {
        $stack = new Arr([$root]);
        $bestNode = null;
        $bestScore = null;

        while ($stack->isNotEmpty()) {
            $currentNode = $stack->_pop();
            if (!$currentNode) {
                continue;
            }

            $this->visitNode($currentNode);

            $children = $currentNode->getChildren();
            $isLeaf = empty($children);

            if ($isLeaf) {
                $score = $currentNode->evaluate();
                if ($bestScore === null || $score > $bestScore) {
                    $bestNode = $currentNode;
                    $bestScore = $score;
                }
            }

            foreach ($children as $child) {
                $stack->push($child);
            }
        }

        // If no leaf was found (degenerate tree), fall back to root.
        if (!$bestNode) {
            $bestNode = $root;
        }

        $this->decisionSelected($bestNode);

        return $bestNode->getValue();
    }
}

