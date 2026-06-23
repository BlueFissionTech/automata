# Automata Examples

This directory contains runnable examples that demonstrate how to use the
Automata library in a DevElation-style, event-driven way. Examples are intended
to be executable documentation: they should run from a clean checkout, avoid
network credentials, and return output that a browser, consumer, or contributor
can inspect without knowing private host applications.

Goals:

- Serve as **tutorials** for each major module (collections, strategies,
  decision trees, expert systems, game theory, genetic algorithms, graph/memory
  /ABS, language, LLM).
- Act as **executable documentation** that proves the systems work, alongside
  the PHPUnit test suite.
- Encourage **DevElation-style programming**, using `Obj`, behaviors, and
  events wherever appropriate.
- Demonstrate current Automata runtime contracts: agents, tools, memory,
  Holoscene, lane pressure, feedback review records, statement bundles, HERD
  signal contracts, and service-free runtime examples.

To run an example:

```bash
php examples/collections_basic.php
```

## Learning Paths

Start with one of these paths depending on the integration surface you are
trying to understand.

### Runtime Contracts And Agents

- `examples/generic/agent_integration_contract.php` – stable feature ids,
  binding templates, catalog filter keys, and production checks.
- `examples/generic/runtime_contracts.php` – capability vocabulary, normalized
  statement bundles, feedback review records, HERD signal results, and lane
  pressure in one service-free payload.
- `examples/generic/agent_lane_pressure.php` – semantic, operational, and
  execution lane-pressure assessment plus the read-only LLM tool wrapper.
- `examples/generic/agent_tool_contracts.php` – `ToolCatalog`,
  `ToolDefinition`, permission metadata, validation, and execution results.
- `examples/generic/agent_memory_hooks.php` – lifecycle memory event capture.
- `examples/generic/agent_governance_review.php` – human review and governed
  task-call patterns.

### Memory, Language, And Comprehension

- `examples/disaster_response/working_memory/run.php` – ABS2 working-memory
  recall over disaster-response context.
- `examples/disaster_response/language_reader/run.php` – language reader
  projection into statements and memory.
- `examples/entity_condition_worldview.php` – prototype-backed `Entity`,
  `Condition`, position, relation, and explanation snapshots.
- `examples/generic/vibe_profile_fillin.php` – declarative generation profiles
  with file-backed and named override examples.

### Decisioning, Planning, And Policy

- `examples/decision_tree_dispatch_policy.php` – decision trees with shared
  method state and injected assessors.
- `examples/expert_logistics_rules.php` – symbolic expert rules for logistics.
- `examples/monte_carlo_route_planning.php` – budgeted Monte Carlo action
  ranking.
- `examples/monte_carlo_tree_search_dispatch.php` – MCTS over a sequential
  dispatch decision tree.
- `examples/generic/disaster_response/initiative_feedback_loop.php` – goal
  projections, observations, assessment strategies, and feedback scoring.

### Graphs, Routes, Anomaly, And Reliability

- `examples/graph_routing_logistics.php` – route planning with fitness-based
  costs.
- `examples/graph_route_allocation_logistics.php` – route allocation where the
  fitness hook can see asset and demand context.
- `examples/anomaly_gateway_basic.php` – multi-detector anomaly scoring on an
  activity.
- `examples/anomaly_logistics_requests.php` – KNN-based anomaly scoring over
  logistics requests.
- `examples/reliability_logistics_routes.php` – beta reliability scoring for
  route options.

### Media And Data Pipelines

- `examples/media_pipeline_intelligence.php` – media ingestion + text pipeline feeding Intelligence.
- `examples/media_pipeline_engine.php` – media ingestion + text pipeline feeding Engine attention.
- `examples/generic/data_ingestion/run.php` – deterministic tabular ingestion
  and routing.
- `examples/disaster_response/social_media_ingestion/run.php` – social media
  input normalization for response workflows.

### Disaster-Response Capstones

- `examples/disaster_response/coordination_pipeline/run.php` – event
  classification, routing, memory, and response summary.
- `examples/disaster_response/capstone_multi_strategy_dashboard/run.php` –
  multi-strategy dashboard-style output.
- `examples/disaster_response/game_theory_allocation/run.php` – allocation
  profiles and payoff strategy shape.
- `examples/disaster_response/genetic_policy_optimization/run.php` – bounded
  policy search over deterministic fixtures.
- `examples/generic/disaster_response/gridworld_demo.php` – richer local
  simulation with service-free entities and strategy classes.

## Example Standards

Many branch-local examples load `examples/bootstrap.php` so they execute against
the worktree source rather than another checkout.

Examples should:

- use `examples/bootstrap.php` instead of requiring Composer directly;
- prefer deterministic, service-free fixtures unless the example is explicitly
  about an external integration;
- emit structured JSON when the output is intended for downstream tooling;
- avoid raw credentials, raw sensitive signals, and consumer-specific package
  fields;
- use current Automata contracts and DevElation helpers when they clarify the
  pattern.

