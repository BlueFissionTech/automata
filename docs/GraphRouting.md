# Automata GraphTheory & Routing Overview

## Purpose

The `Automata\GraphTheory` namespace contains minimal but extensible tools for
graph-based routing and flow planning. It is used here to model disaster
logistics routes (hubs, bridges, highways, hospitals) but is not limited to
that domain.

## Key Classes

- `BlueFission\Automata\GraphTheory\Graph`
  - Represents a directed graph as an adjacency map: `nodeName => [neighbor =>
    edgeAttributes]`.
  - Uses `BlueFission\Arr` for internal storage.
  - Exposes:
    - `addNode(Node $node)` to register a node and its outgoing edges.
    - `shortestPath(string $start, string $end, callable $fitness): array`
      - Dijkstra-style search where the fitness function maps edge attributes
        to a scalar cost (e.g., `time + risk * weight`).
      - Returns an ordered list of node names from `start` to `end`, or an
        empty array if no route exists.
    - `getEdgeAttributes(string $from, string $to): ?array` to retrieve edge
      metadata.

- `Node`
  - Lightweight container for:
    - Node name.
    - Outgoing edges and their attributes.

- `Route`
  - Extends `Obj`.
  - Encapsulates a `path` (array of node names) and a scalar `cost`.
  - Useful for higher-level planning and logging.

- `RoutePlanner`
  - Extends `Obj`, uses `Dispatches` and `Event`.
  - Wraps a `Graph` plus a fitness function to produce `Route` objects via
    `plan($start, $end)`.
  - Emits:
    - `graph.route_planned` with start, end, and route.
    - `graph.route_unreachable` when no path exists.

## Tests

- `tests/Automata/GraphTheory/GraphRoutingTest.php`
  - Validates `shortestPath()` on a small graph:
    - Safest route is chosen under a risk-heavy fitness function.
    - Blocking edges (e.g., marking a highway as blocked) forces rerouting.
    - Attempts to route to disconnected nodes return an empty path.

- `tests/Automata/GraphTheory/RoutePlannerTest.php`
  - Ensures `RoutePlanner`:
    - Returns a `Route` with expected path and positive cost when a route
      exists.
    - Returns `null` when the target is unreachable.

## Example

- `examples/graph_routing_logistics.php`
  - Models:
    - `Hub-1` → `Bridge-1`/`Highway-Loop` → `Hospital-A` with `(time, risk)`
      attributes.
  - Uses `RoutePlanner` to:
    - Compute an initial best route under a time + risk fitness.
    - Recompute after flagging highway edges as `blocked`, showing the fallback
      route and cost.

This example is a good pattern for any routing problem where cost is a function
of edge attributes (time, risk, capacity, etc.).

