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

## Integration Notes

Prefer DevElation helpers for value, array, collection, and configuration behavior when extending these classes. Keep tool implementations thin and reusable; put selection guidance in `ToolDefinition`, execution behavior in the tool, and lifecycle side effects behind hooks.
