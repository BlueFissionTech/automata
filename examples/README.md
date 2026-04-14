# Automata Examples

This directory contains runnable examples that demonstrate how to use the
Automata library in a Develation-style, event-driven way.

Goals:

- Serve as **tutorials** for each major module (collections, strategies,
  decision trees, expert systems, game theory, genetic algorithms, graph/memory
  /ABS, language, LLM).
- Act as **executable documentation** that proves the systems work, alongside
  the PHPUnit test suite.
- Encourage **Develation-style programming**, using `Obj`, behaviors, and
  events wherever appropriate.

To run an example:

```bash
php examples/collections_basic.php
```

Notable examples:

- `examples/anomaly_gateway_basic.php` – multi-detector anomaly scoring on an activity.
- `examples/anomaly_logistics_requests.php` – KNN-based anomaly scoring over logistics requests.
- `examples/graph_routing_logistics.php` – route planning with `Func` fitness plus state-aware assessment.
- `examples/graph_route_allocation_logistics.php` – route allocation where the fitness hook can see asset and demand context.
- `examples/decision_tree_dispatch_policy.php` – decision trees with shared method state and injected assessors.
- `examples/carrier_backed_simulation.php` – simulation over object-backed state via the carrier adapter seam.
- `examples/entity_condition_worldview.php` – worldview-oriented `Entity` and `Condition` snapshot/explain contracts.
- `examples/carrier_adapters_basic.php` – direct `CarrierAdapter` and `StateAdapter` usage over DevElation carriers.
- `examples/jenss_agent_adapter.php` – interpreter-friendly agent state using the prototype-backed `Player`.
- `examples/graph_routing_logistics.php` – route planning with fitness-based costs.
- `examples/monte_carlo_route_planning.php` – budgeted Monte Carlo action ranking over uncertain route outcomes.
- `examples/monte_carlo_tree_search_dispatch.php` – MCTS planning over a small sequential dispatch decision tree.

Additional examples will be added as modules are fleshed out and tested.

Many branch-local examples load `examples/bootstrap.php` so they execute against
the worktree source rather than another checkout.

