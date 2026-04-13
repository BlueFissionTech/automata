<?php

namespace BlueFission\Automata\DecisionTree;

use BlueFission\Automata\Adapters\StateAdapter;
use BlueFission\Func;
use BlueFission\Obj;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\Behaviors\Event;

/**
 * BaseMethod
 *
 * Common base for decision tree traversal methods.
 *
 * Responsibilities:
 * - Provide a consistent `traverse()` contract via IMethod.
 * - Integrate with Develation's event/behavior system so that
 *   callers can observe which nodes are visited and which
 *   decision is ultimately selected.
 */
abstract class BaseMethod extends Obj implements IMethod
{
    use Dispatches;

    protected ?StateAdapter $stateAdapter = null;
    protected ?Func $assessor = null;

    /**
     * Traverse the tree starting from the given root node.
     *
     * Concrete subclasses implement the traversal strategy
     * (depth-first, breadth-first, leaf-only, etc.) and must
     * return the selected node's value as an array or scalar
     * that is meaningful to the caller.
     *
     * @param INode $root
     * @return array The selected node's value.
     */
    abstract public function traverse(INode $root): array;

    /**
     * Hook for when a node is visited during traversal.
     *
     * Subclasses call this at the point where a node is
     * considered for scoring. External observers can listen
     * to `decision_tree.node_visited` to debug or visualize
     * tree walks.
     *
     * @param INode $node
     * @return void
     */
    protected function visitNode(INode $node): void
    {
        $this->dispatch(new Event('decision_tree.node_visited', [
            'node' => $node,
            'state' => $this->stateAdapter?->snapshot() ?? [],
        ]));
    }

    /**
     * Hook for when a decision has been selected.
     *
     * Subclasses invoke this once they have chosen the best
     * node according to their traversal logic. Observers can
     * use the `decision_tree.decision_selected` event for
     * logging, auditing, or downstream orchestration.
     *
     * @param INode $node
     * @return void
     */
    protected function decisionSelected(INode $node): void
    {
        $this->dispatch(new Event('decision_tree.decision_selected', [
            'node' => $node,
            'state' => $this->stateAdapter?->snapshot() ?? [],
        ]));
    }

    public function setState(mixed $state): static
    {
        $this->stateAdapter = StateAdapter::wrap($state);

        return $this;
    }

    public function state(): ?StateAdapter
    {
        return $this->stateAdapter;
    }

    public function setAssessor(Func|callable $assessor): static
    {
        $this->assessor = $assessor instanceof Func ? $assessor : new Func($assessor);

        return $this;
    }

    protected function evaluateNode(INode $node): float|int
    {
        $state = $this->stateAdapter?->snapshot() ?? [];

        return $node->evaluate($state, $this->assessor);
    }
}
