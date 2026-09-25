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
```

The example records 12 synthetic training episodes and one pending review,
projected 12 labelled examples, retained Holoscene episode snapshots, and passed
its JSON round trip. The existing Naive Bayes strategy classified 6/6 separate
fixture requests correctly versus 2/6 for a constant prior. The command emits each
prediction and its expected label, training lineage and boolean conformance gates.

These fixtures intentionally exercise composition with a small, clean vocabulary.
They do not establish open-world accuracy, generative quality, continual learning,
route adaptation, safe operational execution, or production reliability.

## Limits

Snapshot validation accepts only finite scalar and array data. Runtime objects
and resources are rejected; a rejected stream remains owned by its caller.
Persisted records preserve scalar types and detached references, but an evidence
source is not trusted merely because it can be restored. Hosts remain responsible
for admission, privacy, authorization, storage durability, concurrent writes,
provider spend, and any effects that follow a model prediction. Passing this
small fixture does not qualify those capabilities for production use.
