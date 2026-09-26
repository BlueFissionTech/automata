# Cortex composition and conformance

Cortex is a provider-free example assembled from reusable Automata capabilities.
Its fixtures and domain mappings illustrate composition; they do not grant an
application permission to promote models or perform operational actions. Existing
Agent, Statement, Context, Holoscene, strategy, and governance APIs remain intact.

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

## Runnable experiment

Run:

```sh
vendor/bin/phpunit --do-not-cache-result tests/Automata/Learning
php examples/generic/cortex/run.php
php examples/generic/cortex/evaluate.php
php examples/generic/cortex/adapt.php
```

The example records 12 synthetic training episodes and one pending review,
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
continual learning, unrestricted route adaptation, safe operational execution, or
production reliability.

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
conflicting source experience fields or outcomes for the same pair are rejected.
Other outcomes may be appended without changing that pair's source evidence. A learner
exception leaves an uncertain receipt and prevents blind retry. Receipts expose
lineage and application status, but both learner state and deduplication are
process-local. Durable transactional recovery and reconciliation are future work.
Feedback changes scores only; it cannot register models, grant authority or
promote a candidate. Existing performance summaries may report zero for unsampled
resource metrics; those defaults are not measurements.

The route request constructor preserves an explicitly false
`deterministic_preferred` value while retaining the true default. This narrow
compatibility measure addresses the demo's reproduced failure with DevElation's
legacy empty-value assignment behavior. It does not repair generic mutation of
existing objects; construct a new request
when changing that policy until the upstream assignment contract is fixed.

## Limits

Snapshot validation accepts only finite scalar and array data. Runtime objects
and resources are rejected; a rejected stream remains owned by its caller.
Persisted records preserve scalar types and detached references, but an evidence
source is not trusted merely because it can be restored. Hosts remain responsible
for admission, privacy, authorization, storage durability, concurrent writes,
provider spend, and any effects that follow a model prediction. Passing this
small fixture does not qualify those capabilities for production use.
