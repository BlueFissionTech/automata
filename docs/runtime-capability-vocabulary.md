# Runtime Capability Vocabulary

Automata exposes reusable intelligence capabilities through neutral contract
terms. These terms are meant for adapter fixtures, runtime contracts, and
cross-repo coordination where the consumer should not need to know parser
internals, prompt text, or a host application's implementation details.

Use `AgentIntegrationContract::capabilityVocabulary()` when code needs the
machine-readable version of this document.

## Vocabulary Rules

- Prefer capability terms that describe Automata-owned behavior.
- Keep downstream package names out of public contract fields.
- Treat aliases as compatibility hints, not as new owning concepts.
- Map every shared term to stable classes, feature ids, or fixture fields.
- Keep provider and harness details behind adapters.

## Goal

A goal is a desired outcome with criteria, context, expectations, and bounded
decision options.

Stable fields:

- `id`
- `objective`
- `criteria`
- `context`
- `status`
- `expectations`
- `decision_options`
- `score`

Accepted aliases are `objective`, `initiative`, and `desired_outcome`.

Compatibility constraints:

- Use goal for desired outcomes, not for every executable task.
- Use task for runtime work items that may satisfy one or more goals.
- Use criterion or condition for deterministic satisfaction checks.

## Statement

A statement is a normalized semantic assertion or command fragment with
subject, behavior, object, context, and relation data.

Stable fields:

- `type`
- `context`
- `priority`
- `subject`
- `negation`
- `modality`
- `behavior`
- `condition`
- `object`
- `relationship`
- `indirect_object`
- `position`

Accepted aliases are `utterance`, `semantic_statement`, and `claim`.

Compatibility constraints:

- Adapters may keep parser internals private when they emit this normalized
  shape.
- Subject, behavior, and object remain the primary satisfaction fields.
- Context carries scope and normalization metadata; it should not become
  provider prompt text.

## Feedback

Feedback is review, correction, or training-signal evidence attached to a
projection, observation, decision, or generated value.

Stable fields:

- `original_value`
- `corrected_value`
- `actor`
- `reason`
- `confidence`
- `timestamp`
- `trace`
- `policy_strategy`

Accepted aliases are `review`, `correction`, and `training_signal`.

Compatibility constraints:

- Policy gates may stay outside Automata when the host owns the execution
  boundary.
- Correction records should preserve the original value and the evidence that
  justified the change.
- Training signals should identify their strategy and trace instead of only
  storing a score.

## Domain Evaluation

A domain evaluation is a bounded assessment of inputs against domain criteria
that returns scores, tags, decisions, or unmet conditions.

Stable fields:

- `domain`
- `subject`
- `criteria`
- `input`
- `result`
- `score`
- `confidence`
- `unmet_conditions`
- `trace`

Accepted aliases are `evaluation`, `assessment`, and `classification_result`.

Compatibility constraints:

- Use domain as a reusable scope label, not a downstream package name.
- Evaluation should return a result envelope and should not perform
  host-specific side effects.
- Scores and confidence are advisory unless policy or goal criteria bind them
  to a threshold.

## Lane Pressure

Lane pressure is a provider-neutral pressure assessment across semantic,
operational, and execution lanes.

Stable fields:

- `lane`
- `signals`
- `score`
- `level`
- `dominant_signal`
- `recommendations`
- `context`

Accepted aliases are `semantic_operational_execution_pressure`,
`task_pressure`, and `agent_readiness`.

Compatibility constraints:

- Use lanes as an Automata risk-management utility, not as proof of a provider
  internal architecture.
- Semantic pressure belongs to meaning and context, operational pressure
  belongs to policy and runbooks, and execution pressure belongs to tools and
  verification.
- Critical pressure should stop or defer mutations until the named gap is
  resolved.

## Fixture Shape

Adapter conformance fixtures should bind local constructs to Automata feature
ids rather than to implementation classes directly:

```php
$fixture = [
    'construct' => 'capability.statement',
    'feature' => 'agent.capability_vocabulary',
    'inputs' => [
        'subject' => 'user',
        'behavior' => 'requests',
        'object' => 'status',
        'context' => ['scope' => 'session'],
    ],
    'outputs' => [
        'statement',
        'trace',
    ],
];
```

This keeps public contracts reusable while still giving adapters enough shape
to validate compatibility without live model calls.
