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

## Governed Task Calls

External execution surfaces should share the same task boundary whether they are local tools, MCP servers, JSON-RPC calls, or direct APIs. `TaskCallMonitor` provides that boundary. It accepts a task trace, a DevElation-configurable `TaskCallPolicy`, and an optional `HumanReviewGate`.

The monitor emits lifecycle hooks, applies policy, asks for review when configured, executes the call, and writes MCP/RPC/API spans into the trace. Direct service logs can also be captured with `recordTaskCall`, so applications can account for batch or provider-side activity even when the call happened outside the live agent loop.

`HumanReviewGate` is intentionally simple: give it a callable that returns an approval, denial, pending decision, or steering payload. A steering decision lets a reviewer constrain or adjust the request before it executes. This keeps human-in-the-loop approval out of model context while still making review outcomes visible in CPCT traces.

`MCPClient` can use the same monitor, so MCP discovery, resource reads, raw requests, and tool calls are governed and observed instead of bypassing the agent's task accounting. Critical local tools can use the same human gate before `ToolExecutor` runs them.

## Session Scope And Memory

`AgentSession` is the boundary for shared scope. An agent can keep its own prompt context, while the session decides what context, permissions, tools, uploaded inputs, and working memory are available to one or more agents. The session can attach an Automata `IWorkingMemory` implementation, including `Abs2Memory`, so durable memory and Holoscene-compatible working-memory implementations are reached through existing Automata contracts instead of a separate memory silo.

Lifecycle memory logging is intentionally tied to `AgentHook` names rather than memory-specific hook constants. Memory event stores persist hook activity, but the lifecycle belongs to the agent. `InMemoryEventStore` is process-local for tests and short runs. `FileMemoryEventStore` uses DevElation `Disk` storage through `StorageMemoryEventStore`, so applications can replace the storage adapter with another DevElation storage implementation without overriding file and JSON logic.

## Integration Notes

Prefer DevElation helpers for value, array, collection, and configuration behavior when extending these classes. Keep tool implementations thin and reusable; put selection guidance in `ToolDefinition`, execution behavior in the tool, and lifecycle side effects behind hooks.
