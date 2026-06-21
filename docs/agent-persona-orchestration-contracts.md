# Agent Persona Orchestration Contracts

Automata owns reusable agent and persona orchestration contracts for applications
that need guided tasks, tool calls, stateful handoffs, and optional generation.
The contract is provider-neutral: a caller can use deterministic scripts, local
rules, LLM clients, ML classifiers, or external command surfaces without making
the frontend runtime responsible for intelligence behavior.

This document defines the stable shapes an adapter should exchange with
Automata. Runtime classes such as `Agent`, `AgentSession`, `ToolDefinition`,
`ToolExecutionResult`, `TaskTrace`, `Orchestrator`, `AgentState`, and
`AgentIntegrationContract` remain the implementation surface.

## Ownership Boundaries

Automata owns:

- agent and persona metadata
- intent and task descriptors
- tool and skill call contracts
- orchestration result envelopes
- confidence, guardrail, and review metadata
- deterministic versus generative execution policy
- traces that explain how a user-visible task was completed

Application surfaces own:

- presentation text, buttons, scene layout, and user interaction timing
- product-specific routes, permissions, and persistence decisions
- external command execution once Automata has emitted a normalized command
- provider credentials and provider-specific model selection

Conversation scene adapters, including Synthetiq-style scene runtimes, should
treat Automata as the cognition and orchestration source. They may pass scene
state into Automata, but they should not own persona reasoning, tool policy, or
long-running task state.

Command execution adapters, including Wise-style command environments, should
treat Automata output as a normalized task or command request. They own the
actual command execution, environment-specific safety checks, and environment
state writes.

## Contract Shapes

### Agent Persona

```php
$persona = [
    'id' => 'guide.primary',
    'label' => 'Primary Guide',
    'role' => 'guided-task-agent',
    'scope' => 'automata.llm.agent',
    'mode' => 'deterministic-first',
    'goals' => ['answer-user-visible-tasks', 'defer-unsafe-actions'],
    'allowed_tools' => ['knowledge.search', 'task.delegate'],
    'memory_scope' => 'session',
    'guardrails' => ['no_secret_output', 'human_review_for_write'],
    'metadata' => [
        'version' => '1.0.0',
        'owner' => 'automata',
    ],
];
```

Required fields:

- `id`: stable machine id.
- `role`: the persona's orchestration purpose, not a display name.
- `scope`: where state, memory, and governance apply.
- `mode`: one of `deterministic`, `deterministic-first`, `generative`, or `hybrid`.
- `goals`: user-visible task objectives.

### Intent

```php
$intent = [
    'id' => 'intent.open_project_status',
    'input' => 'show project status',
    'normalized' => 'open project status',
    'confidence' => 0.91,
    'source' => 'scene.input',
    'entities' => [
        ['name' => 'project', 'value' => 'current'],
    ],
    'requires_generation' => false,
    'guardrails' => ['read_only'],
];
```

Intent confidence is advisory. Policies, permissions, and task preconditions
still decide whether a task can run.

### Task

```php
$task = [
    'id' => 'task.project_status.2026-06-21',
    'intent_id' => 'intent.open_project_status',
    'persona_id' => 'guide.primary',
    'kind' => 'read_model',
    'status' => 'ready',
    'input' => ['project' => 'current'],
    'context' => [
        'scene_id' => 'consult.phase_zero',
        'session_id' => 'session-123',
    ],
    'policy' => [
        'execution' => 'deterministic',
        'human_review' => 'never',
        'max_tool_calls' => 2,
    ],
];
```

Task `kind` values should stay generic: `read_model`, `write_model`,
`call_tool`, `delegate_task`, `generate_response`, `classify`, or
`summarize`.

### Tool Or Skill Call

```php
$call = [
    'id' => 'call.knowledge.search.1',
    'task_id' => 'task.project_status.2026-06-21',
    'tool' => 'knowledge.search',
    'permission' => 'read',
    'input' => ['query' => 'current project status'],
    'schema' => ['query' => 'string'],
    'requires_approval' => false,
    'guardrails' => ['no_external_write'],
];
```

Tool calls should map cleanly to `ToolDefinition` and `ToolExecutionResult`.
Skill calls use the same shape; the `tool` value names the callable capability,
not a provider-specific implementation.

### Result

```php
$result = [
    'task_id' => 'task.project_status.2026-06-21',
    'status' => 'completed',
    'output' => [
        'summary' => 'Project status is available.',
        'next_actions' => [],
    ],
    'confidence' => 0.88,
    'guardrails' => [
        ['name' => 'read_only', 'status' => 'passed'],
    ],
    'trace_id' => 'trace-task-project-status',
    'handoff' => null,
];
```

Result `status` values are `completed`, `partial`, `blocked`, `denied`,
`needs_review`, or `failed`.

### Confidence And Guardrails

Confidence metadata is bounded from `0.0` to `1.0` and should identify its
source:

```php
$confidence = [
    'score' => 0.84,
    'source' => 'intent-classifier',
    'basis' => ['keyword_match', 'scene_context'],
    'threshold' => 0.75,
];
```

Guardrail metadata should be explicit:

```php
$guardrail = [
    'name' => 'human_review_for_write',
    'status' => 'required',
    'reason' => 'write permission requested',
    'review_surface' => 'application',
];
```

## Deterministic Versus Generative Work

Use deterministic execution when:

- the task maps to a known route, command, state transition, or tool call
- inputs can be validated with a schema
- output text is templated or retrieved
- the task writes state or affects external systems

Use generation or ML when:

- intent classification is ambiguous
- summarization, explanation, or natural-language synthesis is required
- ranked candidate selection benefits from model scoring
- the result is advisory and still guarded by deterministic policy

Hybrid flows should run deterministic policy first, call generation only for the
bounded reasoning or text segment, and then normalize the generated result back
into a task, tool call, or result envelope.

## Integration Examples

### Jenie-Style Proof Guidance

A proof guidance scene can stay deterministic while still using Automata
contracts:

1. Scene input becomes an `Intent`.
2. The selected guide persona supplies `goals`, `allowed_tools`, and guardrails.
3. A deterministic `Task` selects the next guidance step.
4. Automata returns a `Result` with display-ready summary text and optional
   next actions.
5. The presentation runtime renders the result and owns no persona reasoning.

Generation can be enabled later by changing the task policy to `hybrid` for
summaries while keeping navigation, writes, and guardrails deterministic.

### Control Hub-Style Task Delegation

A control surface can delegate work through Automata without coupling UI actions
to intelligence internals:

1. A button or command palette action emits an `Intent`.
2. Automata creates a `Task` with permission and review policy.
3. The task maps to a `ToolDefinition` or nested `Orchestrator` flow.
4. The command environment receives only a normalized tool call or handoff.
5. Automata records trace, confidence, and guardrail results for audit.

Write actions should always pass through deterministic permission checks before
external execution.

## Adapter Boundaries

Conversation scene adapters:

- pass scene id, user input, recent scene state, and safe memory references into
  Automata
- render Automata results
- do not decide persona policy, tool permissions, or orchestration topology

Command execution adapters:

- receive normalized tasks, tool calls, or command handoffs
- execute within their own environment rules
- return execution status and evidence to Automata
- do not mutate Automata session state without a result envelope

Provider adapters:

- translate Automata prompts or tool schemas into provider-specific API calls
- return model output and usage metadata
- do not change Automata task policy or guardrail decisions

## Acceptance Checklist

- Data shapes exist for persona, intent, task, tool or skill call, result,
  confidence, and guardrail metadata.
- Deterministic, generative, and hybrid execution policy is explicit.
- Scene guidance and task delegation examples use the same neutral contracts.
- Conversation scene, command execution, and provider boundaries are separated.
- External adapters can consume the contract without knowing local repo paths or
  private coordination details.
