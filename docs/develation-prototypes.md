# DevElation Prototype Coverage

Automata should use DevElation Prototypes where a class represents a reusable
runtime contract that other intelligence, memory, language, or agent systems may
inspect. Prototype support should not be added just to make simple utility
classes look uniform.

## Current Prototype Carriers

- `LLM\Agent`: agent identity, role, scope, autonomy, control, goals, strategies,
  session scope, and Holoscene association.
- `Language\Statement`: semantic statement state, relations, conditions,
  position, context, explanation, and snapshot projection.
- `Goal\Condition`: condition records that can be snapshotted and evaluated
  against nested context state.
- `Comprehension\Entity`: entity identity, labels, relations, position,
  history, metadata, and explanation.
- `Comprehension\Holoscene`: domain-level scene membership, domain state,
  measures, assessment, history, and explanation.
- `GameTheory\Player`: agent-like game participant identity, goals, criteria,
  strategies, decision history, active strategy, and explanation.

These classes expose durable semantic, state, relation, condition, position, or
domain behavior. They are appropriate prototype carriers because adapters can
inspect them without depending on host-specific internals.

## Applicability Rules

Add or keep prototype support when a class needs one or more of these contracts:

- `Proto`: shared identity, summary, explanation, snapshot, properties, labels,
  history, or cross-runtime metadata.
- `Prototypes\Agent`: role, scope, autonomy, control, goals, criteria,
  strategies, decisions, and agent-facing history.
- `Prototypes\Entity`: semantic entity identity, relations, labels, and
  entity-facing history.
- `Prototypes\Position`: coordinate and dimension metadata that should survive
  adapter and memory boundaries.
- `Prototypes\HasConditions`: reusable condition normalization and evaluation.
- `Prototypes\Domain`: domain membership, domain state, measures, and history.

Avoid prototype support for narrow algorithms, encoders, strategies, gateways,
media processors, DTO-style result wrappers, and simple adapters unless they
become shared semantic or runtime state carriers. Those classes should keep
explicit APIs and typed snapshots where useful.

## Coverage Expectations

Prototype-facing tests should verify the behavior that other packages can rely
on:

- snapshot kind and stable identifying fields;
- labels, relations, conditions, positions, and domain state where applicable;
- agent role, scope, goals, strategies, decisions, and session scope where
  applicable;
- context and metadata preservation without provider-specific fields;
- explanations that summarize the same contract represented by snapshots.

Future prototype additions should include focused tests for the trait contract
being adopted and should document any intentionally excluded neighboring
classes.
