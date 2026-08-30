# Deterministic strategy routing

Automata provides a provider-neutral routing contract for selecting one bounded intelligence strategy without making an LLM, queue, provider, or host scheduler the default execution unit.

## Contract

`StrategyRouteRequest` binds one subject and exact capability version to:

- an ordered list of exact strategy id and version candidates
- data-only input and fixture references
- inert eligibility context
- cost, latency, energy, and invocation limits
- explicitly allowed deterministic, learned, or generative modes
- deterministic-first preference
- an explicit escalation policy
- correlation, causation, and trace lineage

Every route requires an allowed `AutonomyDecision` for the same subject and capability version. Capability registry discovery does not authorize routing by itself.

`IStrategyRouteAdapter` separates the existing engine API from routing. Each adapter supplies a descriptive `StrategyDefinition`, a typed `StrategyEligibility`, a preflight `StrategyUsage` estimate, and a typed `StrategyAdapterResult`. Definitions do not contain handlers, credentials, provider clients, or transport configuration.

`StrategyRouter` rejects malformed requests, unknown versions, capability mismatches, unavailable strategies, disallowed modes, adapters that do not explicitly declare themselves side-effect free, ineligible candidates, and projected budget overruns before execution. Actual usage is accumulated across explicit fallback attempts. An actual budget overrun or an execution exception stops the route because further usage cannot be bounded safely.

## Selection and escalation

Candidate order is stable. When `deterministic_preferred` is true, registered deterministic candidates move ahead of learned and generative candidates while preserving order within each group.

Only `next_eligible` permits another execution after a typed failed result. Learned and generative modes must also appear in `allowed_modes`; escalation policy alone never enables them. Eligibility and estimate failures may continue to another candidate because no strategy was executed.

Completed, denied, exhausted, and failed routes return `StrategyRouteResult` with the selected strategy, output, attempts, accumulated usage, escalation history, authorization evidence, diagnostics, and trace lineage. When a `TaskTrace` is supplied, the router records one `strategy` span.

## Engine composition

`DecisionTreeRouteAdapter` is the first package-owned adapter. It composes `DecisionTree`, `DepthFirstTraceMethod`, inert eligibility, and an explicit usage estimate, then returns both the selected node value and decision path.

Other current engine families remain independently usable and can be exposed through injected adapters:

- `Expert` with facts, rules, and forward or backward approaches
- `Strategy\IStrategy` implementations such as Pattern and Markov prediction
- Simulation and GameTheory
- GoalManager and GoalDecision
- Feedback Assessor, Assessment, and ReviewRecord
- agent orchestration patterns
- provider-owned `IGenerationRunner` implementations

Qualification scores may contribute evidence to an eligibility decision, but qualification is not the universal routing vocabulary.

## Host boundaries

Automata owns route data, exact selection, budget checks, trace spans, and typed outcomes. Hosts and adapters continue to own installation, credentials, billing conversion, queues, persistence, scheduling, transport, provider execution, and side effects. Route adapters must be side-effect free.

See `examples/generic/strategy_routing.php` for an exact autonomy decision and fixture-backed DecisionTree route.
