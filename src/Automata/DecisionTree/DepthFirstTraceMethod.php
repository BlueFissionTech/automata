<?php

namespace BlueFission\Automata\DecisionTree;

use BlueFission\Arr;

/**
 * DepthFirstTraceMethod
 *
 * Depth-first traversal that, in addition to selecting the
 * best-scoring node, records the path of nodes from the root
 * to that node. This is useful when callers want an explicit
 * "decision trace" without baking domain-specific semantics
 * into the tree classes.
 */
class DepthFirstTraceMethod extends BaseMethod
{
    /**
     * @var INode[] Trace from root to best node (inclusive).
     */
    protected array $trace = [];

    /**
     * Traverse the tree and record a trace for the best node.
     *
     * The stack stores pairs of [INode $node, INode[] $pathSoFar],
     * where $pathSoFar is the path from the root to the parent of
     * $node. When a better node is found, we derive its full path
     * as $pathSoFar plus the node itself.
     *
     * @param INode $root
     * @return array The selected node's value.
     */
    public function traverse(INode $root): array
    {
        // Each stack element: [node, pathToParent]
        $stack = new Arr([[$root, []]]);
        $bestNode = $root;
        $bestScore = $root->evaluate();
        $this->trace = [$root];

        while ($stack->isNotEmpty()) {
            $currentPair = $stack->_pop();
            if (!$currentPair || !is_array($currentPair) || count($currentPair) < 2) {
                continue;
            }

            /** @var INode $currentNode */
            $currentNode = $currentPair[0];
            /** @var INode[] $pathToParent */
            $pathToParent = $currentPair[1];

            $this->visitNode($currentNode);

            $score = $currentNode->evaluate();
            if ($score > $bestScore) {
                $bestNode = $currentNode;
                $bestScore = $score;
                $this->trace = array_merge($pathToParent, [$currentNode]);
            }

            $children = $currentNode->getChildren();
            if (!empty($children)) {
                $newPath = array_merge($pathToParent, [$currentNode]);
                foreach ($children as $child) {
                    $stack->push([$child, $newPath]);
                }
            }
        }

        $this->decisionSelected($bestNode);

        return $bestNode->getValue();
    }

    /**
     * Get the trace (sequence of nodes) for the last decision.
     *
     * @return INode[]
     */
    public function getTrace(): array
    {
        return $this->trace;
    }
}

