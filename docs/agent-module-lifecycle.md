# Agent Module Lifecycle Conformance

Automata exposes an additive lifecycle wrapper for trusted `IAgentModule`
implementations. The wrapper keeps host authorization separate from descriptive
feature discovery, preserves trace lineage, and reports bounded execution facts
without claiming controls that synchronous PHP cannot provide.

## Contract

`AgentModuleRunRequest` requires a `GovernanceDecision`. A feature descriptor,
module registration, or prior grant is not execution authority. The host creates
the current decision and supplies optional context, lineage, requested lifecycle
features, cancellation state, and observed limits.

`AgentModuleLifecycle::run()` returns `AgentModuleLifecycleResult` with:

- the module decision and proposed state writes;
- contract version and host authorization evidence;
- run, task, trace, correlation, and causation identifiers;
- status, diagnostics, duration, and termination evidence;
- explicit host ownership of effect authorization and idempotency.

`Agent::runModuleLifecycle()` applies proposed writes only for a completed result
and records an orchestration span. The older `Agent::runModule()` remains the
trusted synchronous compatibility path and retains its existing behavior.

## Supported semantics

- explicit host authorization before invocation;
- confirmed cancellation before invocation;
- normal synchronous results and proposed state writes;
- exception normalization without exposing exception messages;
- post-completion duration measurement;
- stable trace, correlation, and causation lineage.

## Unsupported semantics

The contract reports `unsupported` before invocation when a caller requests
in-flight cancellation, progressive output, resume, hard preemption, or
exactly-once effects. A measured duration overrun is reported only after the
synchronous module has returned. It is not a hard timeout and cannot prove that
module-owned side effects were prevented.

Missing termination, in-flight, uncertainty, or post-terminal effect evidence
remains `null`. `false` and empty collections are reserved for facts supplied or
observed by the host; absence is never normalized into a claim that nothing
happened.

## Host responsibilities

The host owns current authorization, effect isolation, durable idempotency,
preemptive worker controls, retry policy, and reconciliation of uncertain
effects. Modules should return proposed state writes rather than mutating shared
state directly when they need the lifecycle wrapper to gate application.

Run the provider-free fixture:

```bash
php examples/generic/agent_module_lifecycle.php
```
