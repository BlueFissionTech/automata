# Cortex delivery and conformance plan

Cortex is an example assembled from reusable Automata capabilities. It owns its
fixtures, domain mappings and assembly. Generic cognition contracts belong in the
library. Existing Agent, Statement, Context, Holoscene, strategy and governance
APIs remain intact.

The capabilities below describe the current development branch and remain staged
for review. Select a revision containing the contracts before integrating them;
passing examples do not imply a published release or production certification.
For commands and expected gate counts, start with the
[example guide](../examples/generic/cortex/README.md).

## Integration flow

1. Capture an `Experience` from statements and context, then attach explicitly
   observed `Outcome` records. The host decides which sources and labels to trust.
2. Use an `ITrainingAdapter` and `ExperienceRecomposer` to obtain a `TrainingBatch`
   with projection identity and experience/outcome lineage. Train an existing
   candidate through `LearningCoordinator` under explicit training policy and host
   approval; recomposition does not train it automatically.
3. Wrap distinct trained strategies as `ModelCandidate` objects and compare them
   using `ClassificationEvaluator` and a separate labelled holdout.
4. Use `StrategyOutcomeFeedback` to update advisory route scores from admitted
   outcomes, or `ModelLifecycle` to evaluate and request approval for activation.
   These are separate operations: a score update cannot promote a model, and
   reference activation does not update an unrelated router.
5. Declare a response envelope, produce fragments directly or through an
   `AgentResponse` handle, and deliver prepared releases through a host-owned
   executor. Only successful terminal receipts satisfy fragment dependencies.

See [strategy routing](strategy-routing.md), [model activation](model-lifecycle.md)
and [response composition](response-composition.md) for the individual contracts.
The learning demo connects experience capture, training, activation and Agent
responses in one process. Persistent workers and broader strategy/goal integration
remain open. See [continual learning](continual-learning.md).

## Evidence-backed change map

| Surface | Treatment | Evidence or next proof |
| --- | --- | --- |
| `Language/Statement.php`, `Context.php` | Keep; capture snapshots | Input mutation cannot rewrite recorded experience |
| `Comprehension/Holoscene.php` | Keep; use existing `push()` seam | Example records and reviews the experience snapshots |
| `Learning/*` | Add experiences, outcomes, store and projections | `tests/Automata/Learning` and Cortex command |
| `Learning/ClassificationEvaluator.php` | Add held-out classification comparison | Improving candidate recommended; regression, overlap and unreliable evidence rejected |
| `Learning/LearningCoordinator.php`, `TrainingPolicy.php` | Coordinate evidence-triggered isolated candidate training | Accumulation, pressure, approval, uncertain failure, retries and changed future Agent responses |
| `Learning/ModelLifecycle.php` | Own a process-local active reference and revision | Fresh evaluation and explicit approval precede activation; actual inference changes and rollback are demonstrated |
| `Learning/StrategyOutcomeFeedback.php`, `Intelligence.php`, `Strategy/Routing/*` | Bridge explicitly admitted outcomes into advisory scores | Adaptive route changes while eligibility, exact versions, authorization and invocation limits remain enforced |
| `Strategy/IStrategy.php` | Keep interface; adapt batches | Example trains existing Naive Bayes pipeline |
| `Goal/ManagesGoals.php` | Audit and extend shared criteria/dependencies later | Require multi-goal progress and blocked-prerequisite tests |
| `Path/Graph.php`, `Path/Node.php` | Evaluate reuse for composite strategies | Require bounded traversal, fallback, early exit and cancellation |
| `Parsing/*`, DevElation parser | Adapt executable strategies later | Require deterministic output and governed tool calls |
| `LLM/Agent/Memory/*` | Keep event storage; add explicit durable experience adapter later | Require restoration and conflicting-write tests |
| `Response/*` | Add weighted composition and terminal delivery receipts | Progressive output, lost-ack restart, cancellation and fallback are demonstrated with a deduplicating fixture sink |
| `LLM/Agent/Response/*`, `LLM/Agent.php` | Bind synchronous response workers to task/session identity | Governed fixture tools, terminal receipts and correlated TaskTrace events; no interrupted-worker recovery claim |

## Review sequence

1. Experience and training projection foundation, with an executable fixture proof.
2. Attributed feedback and candidate evaluation: measured incumbent/candidate
   comparison, worse-candidate rejection, exact strategy versions and no authority
   changes from learned scores.
3. Response envelopes and composition: weighted completion, required dependencies,
   progressive output, cancellation, resumable emission state and Agent workers.
   Governed process-local model activation and rollback add the next review slice.
4. Composite and scripted strategies: use existing graph/parser seams, enforce
   budgets and governance, and prove fallback and early exit.
5. Goal graph integration: shared criteria, prerequisites, decomposition validation,
   convergence and observed multi-goal progress.
6. Persistence and full conformance: recovery, idempotency, hostile memory input,
   cancellation, concurrency, budgets, traceability and compatibility.

Stack a PR only when it imports an earlier unmerged contract. Otherwise target the
normal base branch independently. Publish exact contract versions and migration
notes when a stage becomes available. No release tag is implied by a passing demo.

## Foundation contract

`Experience::fromStatements()` captures semantic snapshots, context data/tags/
normalizations, caller-supplied trace/provenance and a timestamp. `withOutcome()`
returns another snapshot. Outcome source and optional attribution describe where
evidence came from; they are not proof that a source is trusted. Hosts must apply
their trust and privacy policies before capture and projection.

`IExperienceStore::save()` replaces the current snapshot by id. The reference
implementation is neither durable nor a concurrency-control mechanism. Persisted
records use `schema_version = 1`; restore with `new Experience($record)`. Use
`JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION` when encoding if exact numeric
PHP types must survive JSON round trips.

Adapters declare projection id/version and return `TrainingExample` objects or an
empty iterable. Recomposition validates each source experience and outcome after
extension filters. Identical repeated evidence is deduplicated; conflicting rows
for the same outcome are rejected. Separate adapters can project the same
experience into different strategy-specific representations. Neither recomposition
nor `TrainingBatch` promotes models or mutates live strategies.

## Current experiment

Run:

```sh
vendor/bin/phpunit --do-not-cache-result tests/Automata/Learning
php examples/generic/cortex/run.php
php examples/generic/cortex/evaluate.php
php examples/generic/cortex/adapt.php
php examples/generic/cortex/respond.php
php examples/generic/cortex/agent.php
php examples/generic/cortex/promote.php
php examples/generic/cortex/learn.php
```

The initial run recorded 12 synthetic training episodes and one pending review,
projected 12 labelled examples, retained Holoscene episode snapshots, and passed
its JSON round trip. The existing Naive Bayes strategy classified 6/6 separate
fixture requests correctly versus 2/6 for a constant prior. The command emits each
prediction and its expected label, training lineage and boolean conformance gates.

These fixtures intentionally exercise composition with a small, clean vocabulary.
The candidate-evaluation command reuses those fixtures and existing strategy
implementations. It compares distinct exact model versions, rejects overlap with
either declared training corpus, and recommends only strict improvement meeting
sample/quality/latency policy. It then rejects a worse candidate while leaving the
incumbent installed in the caller's variable. Per-example evidence includes
experience/outcome ids, predictions, failures and elapsed milliseconds. Unknown
cost/energy remain unknown. Sample limits and post-run latency checks are not
cancellation or resource-spend enforcement. Callers provide trusted prediction
implementations and truthful training provenance; undisclosed pretraining and
semantic duplicate detection are outside this evaluator's guarantees.

The adaptation command admits the evaluation's independently labelled outcomes,
records exact strategy versions and context, and routes a new request through the
existing advisor. The selected version changes from the constant prior to Bayes.
It also probes duplicate feedback, deterministic preference, adapter eligibility,
denied authorization, unregistered versions and a zero invocation budget.

These experiments do not establish open-world accuracy, generative quality,
continual learning, safe operational execution, or production reliability.

The response command adds twelve gates around progressive output, required
fragments, successful dependency receipts, stable delivery identity after a lost
acknowledgement, cancellation and deadline fallback. Its single simulated action
is deduplicated by a receiver whose state survives the caller's simulated restart.
This is evidence for the composition protocol; durable storage, concurrent writers,
and production delivery remain open. The Agent command adds synchronous worker
adapters, task/session-bound response handles and correlated TaskTrace events. It
uses real tool approval checks and host fixtures for scope, revoked permission,
cancellation and uncertain effects. This proves the integrated execution path;
authenticated durable receipt adapters and interrupted worker recovery remain open.
See [the response contract](response-composition.md).

The promotion command adds eight gates for host-approved activation and rollback,
rejection, historical request replay and stale revisions. It observes predictions
through the active model reference before and after transitions. This establishes
process-local behavior, without model persistence or deployment. See
[the lifecycle contract](model-lifecycle.md).

All seven commands run in CI and currently expose 78 gates. The learning command
adds 15 gates that connect recorded experience, policy-triggered training, separate
activation approval and receipt-gated Agent responses. The versioned
[`fixture-v1.json`](../examples/generic/cortex/fixture-v1.json) and
[`baseline-v1.json`](../examples/generic/cortex/baseline-v1.json) pin the corpus,
projection lineage and all six expected predictions. Constant and single-wrong
negative controls verify that the regression comparison catches bad output.
Fixture or baseline changes require review and a new version; accepting newly
produced output alone is not validation.

## Attributed feedback contract

`StrategyOutcomeFeedback::apply($experience, $outcomeId)` accepts only an attached
outcome with explicit `strategy_id`, `strategy_version` and `context_key`
attribution. The host decides which evidence to admit. Optional observations under
`feedback` contain finite, nonnegative numbers for `accuracy`,
`prediction_accuracy`, `score`, `confidence`, `latency_ms`, `cost` or `energy`;
quality ratios must be at most one. Null metrics are omitted. Outcome success is
preserved even when false, and numeric zero remains evidence. Unknown fields are
rejected. Strategy ids and versions cannot contain `@`, the existing advisor's
identity separator.

Identical repeated evidence returns false after a successful application;
conflicting evidence for the same experience/outcome pair is rejected. A learner
exception leaves an uncertain receipt and prevents blind retry. Receipts expose
lineage and application status, but both learner state and deduplication are
process-local. Durable transactional recovery and reconciliation are future work.
Feedback changes scores only; it cannot register models, grant authority or
promote a candidate. Existing performance summaries may report zero for unsampled
resource metrics; those defaults are not measurements.

The route request constructor preserves an explicitly false
`deterministic_preferred` value while retaining the true default. This narrow
compatibility measure addresses the demo's reproduced failure with DevElation's
legacy empty-value assignment behavior, tracked in
[DevElation #258](https://github.com/BlueFissionTech/develation/issues/258).
It does not repair generic mutation of existing objects; construct a new request
when changing that policy until the upstream assignment contract is fixed.

## Release gates still open

Current proofs cover snapshots, attributable training data, bounded classification,
advisory route adaptation, receipt-gated Agent responses and process-local model
activation/rollback. Production and broader Cortex integration still require:

- Durable experience, feedback and response stores with authenticated restoration,
  receiver idempotency, concurrent-write rules and interrupted-worker recovery.
- Durable/background training workers, model artifact persistence, richer experiential
  strategy adapters and representative evaluation data for the combined runtime.
- Composite/scripted strategies over existing graph/parser seams, shared goal
  criteria and dependencies, and explicit experience/session/trace integration.
- Integrated multimodal execution, resource budgets and reconciliation when hosted
  execution has uncertain billing or effects.

A version increase is considered only after the relevant PRs are approved and
merged and conformance evidence is reviewed. Maintain the existing prerelease
posture until those gates justify a stronger release claim.

## Primitive helper policy

The learning records, projections, reference store and runnable experiment use
DevElation array, string, numeric, boolean and resource helpers. Prefer fluent
chains for transformations, including map/filter/value pipelines, string
normalization and numeric arithmetic. Keep type predicates explicit before
constructing values so validation does not silently coerce malformed input. The Engine refactor uses
those same primitives while preserving its historical comments and existing
classification and attention behavior. The selected typed predicates and keyed
mapping callbacks are available in the declared DevElation minimum, v1.3.39.

Snapshot validation rejects runtime objects before invoking primitive predicates:
DevElation intentionally unwraps value objects, whereas persisted experience
records accept only plain scalar and array data. The existing `Ref::is` helper
rejects stream resources without taking ownership or closing the caller's handle;
this behavior has regression coverage and is available in DevElation v1.3.39. Explicit JSON flags preserve
floating-point types and throw on encoding failures. Native runtime inspection,
UTC timestamps and clock reads remain where there is no equivalent helper with
the required semantics. Regression coverage preserves scalar types, detached
references, rejection of value wrappers and nonfinite numbers, strategy class
registration, and attention-statistic keys and values.
