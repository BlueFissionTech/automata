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

The default `StructureFactory` still returns the current Automata/DevElation structures, but the interface intentionally returns `mixed` for structure objects. That keeps the call sites open to future bitset, dense-vector, sparse-vector, or Chronicler-backed adapters as long as they provide the same behavioral methods used by the algorithm.

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

Automata issue #76 should remain open for the actual shared-structure migration. Chronicler PR #8 is expected to provide reusable `Vector`, `Set`, `Dictionary`, `Deque`, and `Pile` structures under Chronicler issue #7.

Until that upstream boundary lands, Automata should keep algorithm-specific behavior local and route new generic structure construction through `IStructureFactory` so the eventual migration is adapter work rather than algorithm surgery.
