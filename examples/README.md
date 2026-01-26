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
- `examples/graph_routing_logistics.php` – route planning with fitness-based costs.

Additional examples will be added as modules are fleshed out and tested.

