# Agent Capabilities

Automata agents treat language models as reasoning components and keep execution in deterministic PHP boundaries. The `BlueFission\Automata\LLM\Agent` class coordinates model prompts, registered tools, lifecycle hooks, and later memory or orchestration layers without requiring external agent harnesses.

## Tool Contracts

Tools are registered through `ToolCatalog` and described by `ToolDefinition`. A definition is a DevElation-configurable contract with:

- a precise purpose and decision boundary
- input and output schemas
- permission class and approval requirement
- retry, circuit-breaker, and parallel-safety metadata
- tags, groups, and taxonomy entries for scoped catalog retrieval

The model receives the definition as prompt context, but it never executes the tool directly. `ToolExecutor` validates the proposed input, checks permissions, runs the PHP tool, normalizes the result, and returns structured success or error data.

## Catalog Retrieval

Large catalogs should be narrowed before they enter prompt context. `ToolCatalog` supports constants for filter keys, including category, permission, tags, tag-all matching, groups, taxonomy, include, exclude, limit, and parallel-safety.

Tags work best for lightweight retrieval such as `current`, `read`, or `write`. Groups are for operational bundles such as `agent.lifecycle` or `documents`. Taxonomy entries are axis-based, for example:

```php
[
    'domain' => ['memory'],
    'lifecycle' => ['pre_tool_use', 'post_tool_use'],
    'risk' => ['read_only'],
]
```

This lets applications request a small catalog slice without forcing new subclasses or hard-coded catalog branches.

## Lifecycle Hooks

Agent lifecycle hook names live in `AgentHook` rather than memory classes. They currently cover:

- `SESSION_START`
- `USER_PROMPT_SUBMIT`
- `PERMISSION_REQUEST`
- `PRE_TOOL_USE`
- `POST_TOOL_USE`
- `TURN_STOP`

Hooks are emitted through DevElation so local applications can attach deterministic logging, permission prompts, memory capture, telemetry, or policy checks without spending model context on those decisions.

## Error Semantics

Tool errors should be structured enough for the model and caller to reason from. Validation failures, permission denials, unavailable tools, and open circuits are represented as `ToolExecutionResult` statuses with codes, messages, details, and metadata. The agent can report partial or failed execution instead of silently filling gaps.

## CPCT Telemetry

Cost per completed task, or CPCT, measures the spend attached to a user-visible outcome rather than only provider token volume. `TaskTrace` is the task-scoped trace object. It carries a stable task id, behavioral change events, and spans for agent, model, tool, and orchestration work. `TaskTraceSpan` stores the fields needed to roll up prompt-cache ROI, batch utilization, model-tier routing, wall time, tool spend, and model spend.

`CpctPricing` is DevElation-configurable so model rate cards can be supplied by local configuration. `CpctReport` turns one or more traces into the CPCT distribution and the three operational savings lines:

- cache ROI from cache-hit and cache-write tokens
- batch utilization from batchable and actually batched spans
- tier routing savings from cheaper-model candidates that still meet the SLO

Provider SDK callbacks, queue logs, or offline service reports can write deterministic data into a trace through `recordModelUsage`, `recordBatchUsage`, and `recordRoutingCandidate`. The hooks in `CpctHook` expose those capture points without requiring the model to decide when accounting should happen.

## Session Scope And Memory

`AgentSession` is the boundary for shared scope. An agent can keep its own prompt context, while the session decides what context, permissions, tools, uploaded inputs, and working memory are available to one or more agents. The session can attach an Automata `IWorkingMemory` implementation, including `Abs2Memory`, so durable memory and Holoscene-compatible working-memory implementations are reached through existing Automata contracts instead of a separate memory silo.

Lifecycle memory logging is intentionally tied to `AgentHook` names rather than memory-specific hook constants. Memory event stores persist hook activity, but the lifecycle belongs to the agent. `InMemoryEventStore` is process-local for tests and short runs. `FileMemoryEventStore` uses DevElation `Disk` storage through `StorageMemoryEventStore`, so applications can replace the storage adapter with another DevElation storage implementation without overriding file and JSON logic.

## Orchestration

`Orchestrator` coordinates multi-agent or multi-worker flows. Patterns live under `Agent\Orchestration\Pattern` and implement `IOrchestrationPattern`, so applications can inject new orchestration strategies without modifying the coordinator. Built-in patterns include sequential, fan-out, hierarchical, reflexive, and PIANO-style controller broadcast.

PIANO is modeled as orchestration because the cognitive controller produces a bottlenecked decision and broadcasts it to worker channels such as speech, action, state, and memory. Every pattern can receive session scope, task traces, working memory, and Agent hook events through the same Agent surfaces.

`OrchestratedAgent` lets any orchestration run as a black-box worker inside a parent orchestration. This is useful for hierarchical societies: a PIANO society can include a villager worker whose inner mind is itself a hierarchical lead-plus-counselor orchestration. The wrapper accepts a scoped context allowlist, so the child orchestration only sees the perceptions and shared context granted to that agent rather than the full parent society state. Parent merge logic preserves black-box output under the worker name instead of flattening the inner workers into the larger society output.

## Agent State And Goals

`AgentState` is a DevElation behavioral state machine with isolated channels for goals, observations, decisions, expectations, outputs, social signals, rules, and reflections. PIANO modules read and write those channels while the state machine gates behaviors such as deciding, tool use, speech, and memory updates through active states like observing, reasoning, acting, speaking, reflecting, and socializing.

Goal reasoning lives in Automata's `Goal` namespace instead of inside the cognitive controller. `GoalManager` holds active `Initiative` objects, checks `Condition` and `Criterion` satisfaction from deterministic context, tracks expectations, scores behavior options, and returns bounded `GoalDecision` options. `CognitiveController` now applies a bottleneck over state channels, asks the goal manager for ranked options, and writes the selected option back into state. This keeps prompt/inference work focused on choosing among bounded options rather than inventing every possible next action from raw context.

## Integration Notes

Prefer DevElation helpers for value, array, collection, and configuration behavior when extending these classes. Keep tool implementations thin and reusable; put selection guidance in `ToolDefinition`, execution behavior in the tool, and lifecycle side effects behind hooks.
