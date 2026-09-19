# Cortex delivery and conformance plan

Cortex is an example assembled from reusable Automata capabilities. It owns its
fixtures, domain mappings and assembly. Generic cognition contracts belong in the
library. Existing Agent, Statement, Context, Holoscene, strategy and governance
APIs remain intact.

## Evidence-backed change map

| Surface | Treatment | Evidence or next proof |
| --- | --- | --- |
| `Language/Statement.php`, `Context.php` | Keep; capture snapshots | Input mutation cannot rewrite recorded experience |
| `Comprehension/Holoscene.php` | Keep; use existing `push()` seam | Example records and reviews the experience snapshots |
| `Learning/*` | Add experiences, outcomes, store and projections | `tests/Automata/Learning` and Cortex command |
| `Intelligence.php`, `Strategy/Routing/*` | Keep existing authority/advice split; later bridge attributed feedback | Existing advisor tests cover reranking without bypassing eligibility |
| `Strategy/IStrategy.php` | Keep interface; adapt batches | Example trains existing Naive Bayes pipeline |
| `Goal/ManagesGoals.php` | Audit and extend shared criteria/dependencies later | Require multi-goal progress and blocked-prerequisite tests |
| `Path/Graph.php`, `Path/Node.php` | Evaluate reuse for composite strategies | Require bounded traversal, fallback, early exit and cancellation |
| `Parsing/*`, DevElation parser | Adapt executable strategies later | Require deterministic output and governed tool calls |
| `LLM/Agent/Memory/*` | Keep event storage; add explicit durable experience adapter later | Require restoration and conflicting-write tests |
| Response composition | Add after experience contracts | Require dependency-gated partial emission and no repeated actions |

## Review sequence

1. Experience and training projection foundation, with an executable fixture proof.
2. Attributed feedback and candidate evaluation: measured incumbent/candidate
   comparison, worse-candidate rejection, exact strategy versions and no authority
   changes from learned scores.
3. Response envelopes and composition: weighted completion, required dependencies,
   progressive output, cancellation, and resumable emission state.
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
```

The initial run recorded 12 synthetic training episodes and one pending review,
projected 12 labelled examples, retained Holoscene episode snapshots, and passed
its JSON round trip. The existing Naive Bayes strategy classified 6/6 separate
fixture requests correctly versus 2/6 for a constant prior. The command emits each
prediction and its expected label, training lineage and boolean conformance gates.

These fixtures intentionally exercise composition with a small, clean vocabulary.
They do not establish open-world accuracy, generative quality, continual learning,
route adaptation, safe operational execution, or production reliability.

## Release gates still open

The first slice establishes snapshots and attributable training data. A production
candidate still needs measured route adaptation, safe candidate promotion,
progressive multimodal responses, goal continuity, interruption recovery,
idempotency, concurrency semantics, memory validation, budgets and trace linkage
through governed actions. A version increase is considered only after the relevant
PRs are approved and merged and conformance evidence is reviewed. Maintain the
existing prerelease posture until those gates justify a stronger release claim.
