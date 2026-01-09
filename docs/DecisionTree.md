# Automata DecisionTree Module Overview (Disaster Logistics Contexted but General-Purpose)

## Purpose

The `Automata\DecisionTree` module provides a small, composable decision tree
framework that can be used to implement explainable decision policies. It is
designed to work with different traversal strategies and to integrate with
Develation's behavioral/event system.

In this repo it is exercised with disaster logistics examples, but the module
itself is general-purpose.

## Key Classes

- `BlueFission\Automata\DecisionTree\Node`
  - Encapsulates a decision node.
  - Holds a `value` (typically an associative array describing the node) and an
    evaluation callback.
  - Can have zero or more child nodes.
  - `evaluate()` calls the provided callback with `(value, children)` and
    returns a scalar score.

- `BlueFission\Automata\DecisionTree\DecisionTree`
  - Holds a reference to a root node.
  - `decide(IMethod $method)` delegates traversal and scoring to a strategy.

- `BaseMethod`
  - Common base class for traversal methods.
  - Extends `Obj`, uses `Dispatches`, and emits:
    - `decision_tree.node_visited` when a node is visited.
    - `decision_tree.decision_selected` when a best node is chosen.

- `DepthFirstMethod`
  - Depth-first traversal strategy using `BlueFission\Arr` as a stack.
  - Tracks a global best node by evaluation score.

- `BreadthFirstMethod`
  - Breadth-first traversal strategy using `Arr` as a queue.
  - Also tracks a global best node by evaluation score.

- `LeafOnlyBestMethod`
  - Depth-first traversal that only considers *leaf* nodes when choosing the
    best decision, treating internal nodes as intermediate questions.

- `DepthFirstTraceMethod`
  - Depth-first traversal that records the path of nodes from the root to the
    selected best node.
  - Exposes `getTrace()` so callers can obtain a generic "rule path" without
    adding domain-specific logic to the tree classes.

## Tests

Decision tree behavior is validated in:

- `tests/Automata/DecisionTree/DecisionTreeTest.php`
  - Confirms:
    - Depth-first chooses the best scoring route in a logistics scenario.
    - Node evaluation can depend on child count.
    - Single-node trees produce the root value.
    - Custom `IMethod` implementations work as expected.
    - Breadth-first and depth-first agree on simple trees.
    - Leaf-only strategy ignores non-leaf scores.

These tests use small, deterministic scenarios to guard against regressions in
traversal logic and evaluation semantics.

## Example

- `examples/decision_tree_logistics.php`
  - Builds a small decision tree for choosing a route from a hub to a
    hospital, with routes differing in time and risk.
  - Demonstrates:
    - `DepthFirstMethod` selecting the safest viable route under a risk-heavy
      scoring function.
    - A custom `IMethod` that always returns the root, showing how policies
      can provide alternate views on the same tree.

The example is CLI-first and can be reused as a template for building other
policy trees (e.g., dispatch decisions, escalation rules).

- `examples/decision_tree_dispatch_policy.php`
  - Demonstrates how to build a simple dispatch policy tree whose leaves encode
    decisions (e.g., accept, deny, escalate).
  - Uses `DepthFirstTraceMethod` to obtain both the chosen decision and a trace
    of node IDs/labels that led to it (explainable policy).
