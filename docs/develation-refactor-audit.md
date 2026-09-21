# DevElation Refactor Audit

## Current Slice

This pass moves the training-data and feature-vector paths toward DevElation-first primitives and explicit dependency injection.

The new `BlueFission\Automata\Support\IStructureFactory` boundary centralizes construction for:

- `Arr`
- `Vec`
- `Dict`
- `Set`
- `Collection`
- normalized value-list extraction
- filled scalar buffers

The default `StructureFactory` now resolves the root DS primitives through Chronicler-backed classes (`BlueFission\Vec`, `BlueFission\Dict`, and `BlueFission\Set`). The interface intentionally returns `mixed` for structure objects. That keeps the call sites open to future bitset, dense-vector, sparse-vector, or custom training-data adapters as long as they provide the same behavioral methods used by the algorithm.

## Refactored Paths

These classes now accept injectable structure factories:

- `Encoding\CategoricalEncoder`
- `Encoding\FeatureEncoder`
- `Normalization\NumericalScaler`
- `Analysis\KNearestExplorer`
- `Strategy\KNearestRegression`
- `Strategy\KNearestPrediction`
- `Feature\InteractionFeatures`
- `Feature\ExtendedInteractionFeatures`
- `Feature\PolynomialFeatures`
- `Feature\Selection\VarianceThresholdSelector`

`KNearestRegression` now delegates neighbor search to `KNearestExplorer` instead of carrying its own raw sorter and distance implementation. That gives future nearest-neighbor/vector search swaps one primary surface.

## Remaining Audit Findings

Raw PHP helpers still appear in the codebase. They fall into three categories:

- Boundary checks: `is_array`, `is_string`, `isset`, `file_exists`, `function_exists`, and JSON/HTTP/parser edge handling. These can remain where they guard external input or extension availability.
- Math primitives: distance, probability, decay, and scoring logic. These should move to `Num` when the value is carried or mutated through a method, but scalar one-liners at algorithm boundaries are lower-risk.
- Structure construction and training splits: these should prefer `IStructureFactory`, `Arr`, `Vec`, and future Chronicler-backed adapters. This slice covers the highest-impact training/vector paths; text predictors and memory scoring are the next likely candidates.

## Chronicler Migration Boundary

Automata now consumes Chronicler for the shared root public class names: `BlueFission\Vec`, `BlueFission\Set`, `BlueFission\Dict`, `BlueFission\Deq`, `BlueFission\Pri`, and `BlueFission\Pile`.

Those root classes are intended to remain `Val` / `IVal` primitive-style structures with php-ds backing when available and array fallback when not. They should be treated as dependency-injectable traversable value primitives for larger dataset work, not as Obj/IObj storage structures.

Chronicler storage internals such as `WeightedCollection`, `PriorityQueue`, and descriptive storage structure classes remain under the Chronicler storage namespace. Automata should target the short root classes for primitive-style DS migration, and use Chronicler storage/ranking internals only where the behavior is actually storage-oriented.

`BlueFission\Automata\Collections\OrganizedCollection` is now a deprecated compatibility adapter over `BlueFission\Chronicler\Storage\Structures\WeightedCollection`. Existing Automata surfaces that typehint or expose `OrganizedCollection` can stay stable while downstream libraries migrate.

New weighted/ranked storage should use Chronicler `WeightedCollection` directly when callers need generic ranking, reinforcement, decay, statistics, or storage semantics. Keep `OrganizedCollection` only where the Automata API contract already exposes it or where behavior/handler collections still depend on its legacy return shapes. If downstream consumers move off `OrganizedCollection`, it can be sunset in a later major-compatible deprecation plan.

## Cortex implementation conventions

Learning, response composition, workflows, sensory capture and script execution follow the same DevElation conventions as the rest of Automata:

- Keep arrays in `Arr` through meaningful transformations such as `filter()->map()->values()`. Materialize with `val()` at a typed array or serialization boundary. Preserve map keys when they carry lineage or node identities.
- Use `Str` for normalization, splitting and byte lengths, after validating the input's actual type. Use `Num` for accumulated values, bounds and unit conversions; name intermediate values when a terminal operation ends a chain.
- Keep strict type, finite-number and schema predicates before constructing value objects. Primitive construction must not turn malformed evidence into an accepted record.
- Use existing fluent object APIs where they preserve ownership. Snapshot data explicitly when creating another request; cloning an `Obj` can retain shared value wrappers. Empty, false and zero fields must survive constructor defaults and record replacement.
- Keep state transitions, authorization, generator dispatch and uncertain side effects in explicit execution order. A transformation callback must not hide or reorder these operations.

Some native operations have a distinct contract and remain deliberate. Canonical maps use `ksort(..., SORT_STRING)` because value sorting loses key identity. Workflow validation retains `array_unique(..., SORT_REGULAR)` because `Arr::unique()` uses string comparison. Sampling uses `floor`, while elapsed-time receipts use `ceil`; nearest rounding is not equivalent. JSON, binary float encoding, hashes and runtime/extension predicates remain boundary operations. `Str::replace()` accepts scalar strings, so array-pattern replacement must retain its existing semantics. `Arr::merge()` recursively combines arrays and must not substitute for exact field replacement.

Refactors are checked against the existing behavioral suites and executable Cortex examples, particularly strict rejection, detached snapshots, false/zero/null payloads, canonical fingerprints, authorization and cancellation receipts. See [the Cortex examples](../examples/generic/cortex/README.md) for the runnable gates; helper use alone does not establish correctness.
