# Agent Capabilities

Automata agents treat language models as reasoning components and keep execution in deterministic PHP boundaries. The `BlueFission\Automata\LLM\Agent` class coordinates model prompts, registered tools, lifecycle hooks, and later memory or orchestration layers without requiring external agent harnesses.

`Agent` is also a DevElation prototype carrier: it extends `Obj` and uses the shared `Proto` and `Prototypes\Agent` traits. That gives interpreter adapters a consistent way to inspect agent role, scope, autonomy, control, goals, strategies, decision history, and snapshot metadata without inventing a parallel identity structure. Automata-specific runtime work still lives in the Agent, session, tool, governance, memory, orchestration, state, and telemetry classes below.

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

## Governed Task Calls

External execution surfaces should share the same task boundary whether they are local tools, MCP servers, JSON-RPC calls, or direct APIs. `TaskCallMonitor` provides that boundary. It accepts a task trace, a DevElation-configurable `TaskCallPolicy`, and an optional `HumanReviewGate`.

The monitor emits lifecycle hooks, applies policy, asks for review when configured, executes the call, and writes MCP/RPC/API spans into the trace. Direct service logs can also be captured with `recordTaskCall`, so applications can account for batch or provider-side activity even when the call happened outside the live agent loop.

`HumanReviewGate` is intentionally simple: give it a callable that returns an approval, denial, pending decision, or steering payload. A steering decision lets a reviewer constrain or adjust the request before it executes. This keeps human-in-the-loop approval out of model context while still making review outcomes visible in CPCT traces.

`MCPClient` can use the same monitor, so MCP discovery, resource reads, raw requests, and tool calls are governed and observed instead of bypassing the agent's task accounting. Critical local tools can use the same human gate before `ToolExecutor` runs them.

Feedback, correction, and training-signal evidence should use
[Feedback Review Records](feedback-review-records.md) when adapters need a
durable review envelope. Policy gates may still live in the host runtime or
Automata governance layer; the review record stores the outcome and evidence.

## Session Scope And Memory

`AgentSession` is the boundary for shared scope. An agent can keep its own prompt context, while the session decides what context, permissions, tools, uploaded inputs, working memory, and Holoscene episodes are available to one or more agents. The session can attach an Automata `IWorkingMemory` implementation, including `Abs2Memory`, so durable memory and Holoscene-compatible working-memory implementations are reached through existing Automata contracts instead of a separate memory silo.

Sessions can also attach a `Holoscene` directly. This lets interpreter adapters project sensory data, statements, scenes, and narrative episodes into the comprehension layer while still keeping access scoped by the session. Agents expose that Holoscene through prototype metadata by `holoscene_id`, so adapter runtimes can inspect the association without forcing raw scene data into prompt context.

Lifecycle memory logging is intentionally tied to `AgentHook` names rather than memory-specific hook constants. Memory event stores persist hook activity, but the lifecycle belongs to the agent. `InMemoryEventStore` is process-local for tests and short runs. `FileMemoryEventStore` uses DevElation `Disk` storage through `StorageMemoryEventStore`, so applications can replace the storage adapter with another DevElation storage implementation without overriding file and JSON logic.

## Orchestration

`Orchestrator` coordinates multi-agent or multi-worker flows. Patterns live under `Agent\Orchestration\Pattern` and implement `IOrchestrationPattern`, so applications can inject new orchestration strategies without modifying the coordinator. Built-in patterns include sequential, fan-out, hierarchical, reflexive, and PIANO-style controller broadcast.

PIANO is modeled as orchestration because the cognitive controller produces a bottlenecked decision and broadcasts it to worker channels such as speech, action, state, and memory. Every pattern can receive session scope, task traces, working memory, and Agent hook events through the same Agent surfaces.

`OrchestratedAgent` lets any orchestration run as a black-box worker inside a parent orchestration. This is useful for hierarchical societies: a PIANO society can include a villager worker whose inner mind is itself a hierarchical lead-plus-counselor orchestration. The wrapper accepts a scoped context allowlist, so the child orchestration only sees the perceptions and shared context granted to that agent rather than the full parent society state. Parent merge logic preserves black-box output under the worker name instead of flattening the inner workers into the larger society output.

## Agent State And Goals

`AgentState` is a DevElation behavioral state machine with isolated channels for goals, observations, decisions, expectations, outputs, social signals, rules, and reflections. PIANO modules read and write those channels while the state machine gates behaviors such as deciding, tool use, speech, and memory updates through active states like observing, reasoning, acting, speaking, reflecting, and socializing.

Goal reasoning lives in Automata's `Goal` namespace instead of inside the cognitive controller. `GoalManager` holds active `Initiative` objects, checks `Condition` and `Criterion` satisfaction from deterministic context, tracks expectations, scores behavior options, and returns bounded `GoalDecision` options. `CognitiveController` now applies a bottleneck over state channels, asks the goal manager for ranked options, and writes the selected option back into state. This keeps prompt/inference work focused on choosing among bounded options rather than inventing every possible next action from raw context.

The default classes are dependency-injection examples as much as concrete implementations. `GoalManager` implements `IGoalManager` and uses `ManagesGoals`; custom managers can implement the same interface and import the trait when they only need to adjust persistence, weighting, or constructor policy. `CognitiveController` implements `IStateController` and uses `ControlsAgentState`; custom controllers can import that trait to keep the standard bottleneck, goal recommendation, and state-write behavior while overriding only the decision context or option selection. `AgentState` accepts an `IGoalManager`, and `Agent::setCognitiveController()` accepts an `IStateController`, so applications can swap either side through normal dependency injection.

## Lane Pressure Management

Agent runtimes often separate meaning, policy, and concrete action even when they use different labels for those layers. Automata models that pattern as provider-neutral lanes rather than as a vendor-specific contract:

- semantic: intent, context, ambiguity, and meaning pressure
- operational: policy, permission, runbook, budget, and coordination pressure
- execution: tool, sandbox, mutation, runtime, and validation pressure

`AgentLane` describes the stable lane metadata. `LanePressureManager` accepts normalized lane metrics and returns the dominant lane, pressure levels, dominant signals, and bounded recommendations. `LanePressure` exposes the same assessment as a deterministic read-only LLM tool for agents that want the model to ask for a lane-pressure report without letting the model perform the assessment itself.

`LanePressureProfile::longHorizonTask()` seeds metrics from common long-running-agent scaffolding: spec clarity, source maps, durable memory, runbooks, milestones, audit logs, verification, observability, isolated workspaces, repair loops, rollback plans, local governance, and tool failures. Missing or weak scaffolding becomes pressure in the lane that can actually reduce the risk.

Use this when an application needs to decide whether to summarize context, request approval, split execution into smaller calls, or stop mutations until a lane pressure is resolved. Do not use it as proof that every provider shares the same internal architecture; it is an Automata utility for a common agent-design pressure pattern.

## Integration Contract Template

`AgentIntegrationContract` exposes Automata's stable agent feature surface as deterministic metadata for external adapters. It does not execute tools or prompts, and it does not know which descendant or application library will consume it. Instead, it names supported feature ids, owning classes, lifecycle hooks, tool catalog filter constants, neutral construct hints, a reusable contract template, and production acceptance checks that other libraries can use to publish their own upstream-facing contracts.

Control-surface and persona adapters should also bind to the
[Agent Persona Orchestration Contracts](agent-persona-orchestration-contracts.md)
for neutral persona, intent, task, tool, result, confidence, and guardrail
shapes.

Runtime contract adapters should use the
[Runtime Capability Vocabulary](runtime-capability-vocabulary.md) for neutral
goal, statement, feedback, domain evaluation, and lane-pressure terms.

The first contract version covers:

- agent runtime configuration and execution
- tool contracts, catalog filtering, and structured results
- lifecycle hooks for deterministic adapter behavior
- session scope, permissions, context, and working memory
- Holoscene comprehension, scenes, episodes, assessments, and narrative logs
- governance, human review, MCP/RPC/API task-call monitoring
- orchestration patterns, nested orchestrations, and PIANO flows
- behavioral state, goals, criteria, expectations, and bounded decisions
- CPCT telemetry and runtime security validation
- provider-neutral lane pressure assessment
- package-neutral runtime capability vocabulary

Adapters should bind to the contract's feature ids rather than hard-coding prompt text or concrete class internals. Automata provides `contractTemplate()` and `bindingTemplate()` so adapter libraries can decide their own construct names, syntax, query language, ingestion flow, and conformance fixtures while pointing back to stable Automata feature ids. Sibling libraries may coordinate with one another, but Automata should not carry descendant-specific binding maps.

## Integration Notes

Prefer DevElation helpers for value, array, collection, and configuration behavior when extending these classes. Keep tool implementations thin and reusable; put selection guidance in `ToolDefinition`, execution behavior in the tool, and lifecycle side effects behind hooks.
