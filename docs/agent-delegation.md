# Agent delegation contracts

Automata models hierarchical delegation as explicit data rather than implicit prompt convention. The contracts are provider-neutral and can be used with local models, hosted model clients, deterministic workers, or mixed systems.

## Contract boundary

- `AgentProfile` identifies a worker and advertises its capabilities.
- `DelegationGoal` describes the outcome and may reference a broader initiative through `initiative_id`.
- `DelegationTask` describes the bounded unit of work for that goal.
- `DelegationRequest` binds source and target identities, context, tools, budgets, policies, completion criteria, and lineage.
- `CapabilityGrant` authorizes one capability from one source to one target. Grants are non-transitive unless a caller explicitly creates another grant.
- `DelegationResult` preserves output, evidence, diagnostics, status, and correlation lineage.
- `DelegationMessage` carries progress, evidence, or peer handoff payloads without widening the original request.

`DelegationWorker` adapts these contracts to `OrchestrationConfig::HIERARCHICAL`. It rejects target mismatches and missing grants before invoking a handler, stops cancelled or expired work, and only exposes explicitly allowed peer results. The hierarchical pattern remains the execution adapter; the delegation objects are the stable semantic and operational contract.

## Bounded peer collaboration

Peer results are private by default. The receiving profile must advertise `peer.results.read`, and its request must set `allow_peer_results`, list the permitted peer agent IDs, and include an exact grant for that capability. This keeps a coordinator in control of each handoff while allowing ordered specialists to build on prior evidence.

## Status handling

Terminal statuses are `completed`, `partial`, `rejected`, `failed`, `cancelled`, and `timed_out`. A hierarchical run reports `partial` when usable specialist output exists alongside a terminal failure. Supervisor rejection or failure prevents worker dispatch.
