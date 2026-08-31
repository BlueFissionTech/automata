# Capability registry and autonomy

Automata separates capability discovery from execution authority. The registry describes what a runtime or adapter can do; an autonomy packet records whether one exact subject may request one exact capability version within explicit limits.

## Capability definitions

`CapabilityDefinition` records a stable capability id, version, owner, inputs, outputs, constraints, risk, evidence, and availability. `CapabilityRegistry` resolves only exact id and version pairs. Registry entries are descriptive data: they do not contain handlers, credentials, transports, installation steps, or implied permission to execute.

Availability is explicit. Only `available` and `degraded` definitions can proceed to authorization. `unknown` and `unavailable` definitions fail closed.

## Scoped autonomy

`AutonomyPacket` binds:

- one subject identity
- requested capability id and version pairs
- exact `AutonomyGrant` records
- constraints and limits
- approval state and reference
- packet and grant expiry
- revocation state and reason
- evidence, correlation, causation, and trace lineage

Authorization returns an `AutonomyDecision`; it never executes a capability. Unknown or unavailable capabilities, pending approval, expired or revoked packets, unrequested capabilities, mismatched subjects, and missing, expired, revoked, or insufficient grants return typed denied decisions. Grants are non-transitive and default to non-transferable.

## Existing boundaries

- `ToolCatalog` remains the executable-tool discovery surface. A tool definition may advertise a capability id, but the capability registry does not execute it.
- Delegation `CapabilityGrant` remains the coordinator-to-worker grant used by hierarchical orchestration. An autonomy grant can describe the wider host authorization boundary, but it does not make delegation grants transitive.
- Governance and human-review classes remain responsible for host review workflows. The packet only carries the resulting approval state and reference.
- Providers, credentials, installation, transport, side effects, and usage accounting remain adapter or host responsibilities.
- `StrategyRouter` may consume an allowed `AutonomyDecision`, but it rechecks the exact subject and capability version and does not treat registry discovery as authority.

See `examples/generic/agent_capability_registry.php` for a deterministic allowed decision and a denied version mismatch.
