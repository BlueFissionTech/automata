# BlueFission Automata – Product Specification

## 1. Purpose and Philosophy

`bluefission/automata` is the baseline AI orchestration library for Blue Fission
products. It is designed to:

- Provide a large set of **interchangeable strategies** for solving AI problems.
- Avoid monolithic “single model” solutions by **routing each problem/intent**
  to the most appropriate strategy or model.
- Make it easy to **quickly iterate, compare, and evolve** strategies over time.
- Stay compatible with, and build on top of, **Develation**’s object and
  behavior system.

Automata treats AI capabilities as pluggable, composable building blocks:

- Low‑level data structures and collections optimized for large data and
  streaming inputs.
- Reusable strategies for classification, prediction, search, and control.
- Higher‑level systems (expert, game, genetic, NLP, memory) that can be
  recombined for different products.

The long‑term goal is to make Automata a **unified interface over many AI
systems**:

- Cloud AI: OpenAI, Claude, Amazon SageMaker, etc.
- Classical ML: Bayesian models, boosted trees (e.g., XGBoost‑style),
  k‑NN, n‑grams, decision trees, Markov models, etc.
- Neural approaches: RNNs, GANs, state‑space models, image models.

Automata should make it straightforward to experiment with multiple options,
measure them, and route traffic to the best performing strategy for a given
intent.

## 2. Relationship to Develation

Automata depends on `bluefission/develation` and inherits its philosophy:

- **Interchangeability** – most modules are wired through interfaces or
  behavioral/event abstractions so they can be swapped at runtime.
- **Rapid application development** – using common base classes (e.g. `Obj`) and
  traits (e.g. `Dispatches`) to reduce boilerplate and focus on behavior.
- **Event‑driven** – Automata modules emit and respond to events rather than
  being tightly coupled.

Develation provides:

- `BlueFission\Obj` – base object with configuration and behavior wiring.
- Behavioral traits and events, used to connect Automata’s inputs, strategies,
  and services.
- Template and parsing infrastructure that some LLM and language components
  depend on.

Automata should remain a **thin, opinionated AI layer on top of Develation**,
not a replacement for its collections or behavior system. When there is overlap
(e.g., collections), Automata’s versions are tailored for heavy AI use and large
data sets.

### 2.1 Carrier Signature and Adapters

Develation's practical "single signature" is carrier-based:

- `Val` / `Arr` for values and structured state
- `Obj` for object-backed state via `field()` / `assign()` / `toArray()`
- `Data` / `IData` for store-backed state with `read()` / `write()` /
  `contents()`

Automata should build on that carrier signature rather than forcing unrelated
utilities into one worldview superclass. The preferred pattern is:

- use prototype traits only on carriers that actually benefit from them
- use adapters when a utility only needs a normalized state/object/store surface
- keep mechanics injectable so utilities share data shape, not internal logic

Initial adapter layer in Automata:

- `CarrierAdapter` for `Obj`
- `StateAdapter` for array/`Arr`/`Obj`/`IData`
- `StoreAdapter` for `IData`

Evaluation and orchestration seams that previously depended on raw PHP
callables and ad hoc scalar helpers should prefer Develation-native surfaces
when practical:

- `Func` for strategy hooks, assessors, classifiers, providers, and fitness
  callbacks
- `Num` for score normalization, budgets, mutation math, and timing math
- `Arr` / `Collection` for carrier-aligned list and map transforms
- `Str` for normalization of labels and identifiers

## 3. Core Concepts

### 3.1 Intelligence Engine

`src/Automata/Intelligence.php` is the orchestrator:

- Registers and manages multiple `IStrategy` implementations in a weighted
  `OrganizedCollection`.
- Groups strategies by **data type** or **intent** (`DataGroup`).
- Detects input type via `InputTypeDetector` and dispatches to appropriate
  strategy groups.
- Benchmarks strategies (via `BenchmarkService`) during training and prediction,
  then uses these measurements to sort/weight strategies.
- Emits prediction events (`PREDICTION_EVENT`) with context, enabling external
  logging, auditing, and meta‑strategies.

Key behaviors:

- `train(dataset, labels)` – trains strategies, measures accuracy and execution
  time, and computes scores.
- `predict(input)` – uses the current “best” strategy.
- `approvePrediction()` / `rejectPrediction()` – feedback loop that adjusts
  weights based on real‑world performance.

### 3.2 Strategies and Data Groups

The `Strategy` module formalizes interchangeable AI strategies:

- `IStrategy` – contract for trainable and/or predictive units.
- Concrete strategies – k‑NN, Markov text, n‑gram text, naive Bayes, neural
  image classifiers, etc.
- `DataGroup` – groups strategies by data type, domain, or intent.

Intelligence and higher‑level systems should treat these strategies as
**opaque, pluggable units**, interacting only through interfaces and events.

### 3.3 Inputs, Sensory, and Input Types

The `Sensory` module handles **incoming information**:

- `Input` – source of signals or raw data.
- `InputType` / `InputTypeDetector` – classify input as text, numeric, time
  series, graph, image, etc.

Design goals:

- Handle **large, streaming inputs** efficiently.
- Normalize inputs so that strategies receive consistent representations.
- Integrate cleanly with collections and memory systems.

### 3.4 Collections and Core Data Structures

Basic data structures such as `Deq`, `Dict`, `List`, `Pile`, `Pri`, and `Vec`
are **foundational**:

- Optimized for AI workloads where PHP arrays and Develation collections may be
  too general or memory‑heavy.
- Intended to support large data sets, sliding windows, and frequent updates.

They must be:

- Thoroughly tested for correctness and edge cases.
- Benchmarked against native arrays and Develation collections for real
  workloads.
- Documented clearly so downstream modules (NLP, memory, graph, etc.) can rely
  on them.

### 3.5 Memory, Graphs, and ABS/ABStract Systems

Automata’s **memory system** is designed to:

- Represent concepts and experiences in a **graph** structure.
- Track relationships, similarity, and paths between concepts.
- Serve as a **working memory** for ongoing problem solving.

The ABS system (Absolute/Abstract, ca. 2004) provides:

- A vector‑like map of concepts to support:
  - semantic search,
  - generalized experiences,
  - “embedding‑like” operations.
- A bounded working memory, not a long‑term data warehouse.

Memory is closely tied to:

- `Path` – traversal, pathfinding, similarity, and structural operations.
- `Comprehension` and Holocene – higher‑level management and traversal of
  experiences.

Any changes to graph or memory APIs must consider **ABS** and **Holocene**
compatibility.

### 3.6 Language and NLP

The `Language` module is a rule‑based NLP system that:

- Tokenizes, parses, and interprets natural language.
- Supports a **simple, rule‑driven language** that can be used for:
  - human language (spoken / written),
  - domain‑specific languages,
  - potentially “original” computer languages.

It is already used in other libraries (e.g. `bluefissiontech/synthetiq`), so:

- Backwards compatibility and behavior stability are important.
- Tests must be strong enough to detect regressions when refactoring.

Documenter, Grammar, Interpreter, Tokenizer, Walker, and related classes should
be treated as production‑critical components.

The lightweight Markov predictors are intended for local phrase continuity and
small-to-moderate catalogs. Use `MarkovPredictor` when a single previous token
is enough context. Use `TrigramMarkovPredictor::addSentence()` for incremental
updates, and `TrigramMarkovPredictor::addSentences()` or `train()` when loading
a catalog in bulk. Configure trigram bounds when training should remain memory
and setup-time predictable.

### 3.7 Expert Systems

The `Expert` folder focuses on **fact‑based expert systems**:

- Rules and facts represented explicitly.
- Inference over these structures to produce decisions or explanations.
- Intended to be configurable by other models (e.g. LLMs can assemble rule
  sets, which are then executed by the expert system).

Tests should:

- Capture core inference semantics.
- Cover negative and ambiguous cases.
- Ensure rule and fact representations remain stable.

### 3.8 Game Theory and Simulation

The `GameTheory` module provides:

- A simple system to model games with rules and players.
- Support for state machines that transition according to game rules.

Goals:

- Make it easy to define a game:
  - state representation,
  - legal actions,
  - payoffs and utilities.
- Enable agents (human or automated) to experiment with strategies and payoffs.

Simulation should be able to run against carrier-backed state without implying
that simulation is the canonical domain implementation. Runtime state may come
from plain arrays, `Arr`, `Obj`, or `IData` via adapters.

Decision, pathing, genetic, feedback, and intelligence utilities should follow
the same pattern: consume adapter-backed state and DevElation-native evaluator
objects without requiring one canonical domain implementation.

### 3.9 Genetic Algorithms

The `Genetic` module:

- Evolves candidate solutions via fitness functions and simple genetic
  operators.
- Should make it easy to plug in new fitness functions and solution
  representations.

It is a natural complement to:

- Strategy selection (evolving ensembles).
- Hyperparameter search for other models.

### 3.10 Monte Carlo Search and Tree Search

The `MonteCarlo` module provides:

- Budgeted stochastic evaluation of candidate actions or states.
- Deterministic seeded rollouts for reproducible tests and examples.
- Tree search over sequential decisions using Monte Carlo Tree Search (MCTS).

Goals:

- Make it easy to evaluate competing actions when the environment is noisy or
  partially simulated.
- Reuse the same rollout primitives for direct action ranking and for MCTS.
- Support callback-driven integration with game, simulation, path, and
  strategy-oriented modules without forcing a single state representation.

Core expectations:

- Monte Carlo search should report visits, total reward, mean reward, and best
  observed reward for each candidate action.
- MCTS should implement selection, expansion, simulation, and backpropagation
  with a configurable exploration/exploitation balance.
- The subsystem should remain simple enough to serve as a planning primitive
  for logistics, dispatch, and other decision-heavy examples.
- The subsystem should follow DevElation-style behavior/config patterns so
  rollout engines can be observed, budgeted, and composed into larger
  intelligence loops.

Worldview dependency:

- Higher-order abstractions for `Proto`, `Position`, `Blueprint`, and `Agent`
  should live upstream in Develation and be consumable here once stabilized.
- Automata should treat those as worldview primitives that shape data typing,
  feature extraction, simulation state, and JenSS-facing semantics without
  hard-coding one domain model.

### 3.11 Analysis, Classification, and Decision Trees

The `Analysis` folder is focused on **classification**:

- Current classifiers are opinionated; the goal is to add more generic,
  reusable classifiers with clear interfaces.
- Support for building ensembles from multiple classifiers.

### 3.12 Anomaly and Behavioral Risk

The `Anomaly` module provides a toolkit for fraud and anomaly detection:

- Multi-detector gateway that can score activity/fingerprint inputs.
- Strategy wrappers around classical ML (random forest, logistic regression,
  k‑means) with optional external backends (XGBoost, isolation forest).
- Activity + fingerprint + signature primitives for behavioral biometrics,
  device/location heuristics, and context-driven scoring.
  - Signatures support heuristic matching against fingerprints.

The `DecisionTree` folder:

- Represents low‑hanging fruit for early, complete testing.
- Needs more methods, clearer intent, and documentation for each method.
- Should provide a canonical, well‑tested implementation of decision trees in
  the library.

### 3.13 LLM and External Model Integration

The `LLM` module integrates:

- Template and parsing systems (via Develation and other libraries).
- HTTP clients and remote APIs (e.g., OpenAI, Gemini).
- Provider-neutral agent lane pressure utilities for semantic, operational, and
  execution load.

Intent:

- Provide a **structured interface** around LLM calls.
- Allow LLMs to configure and supervise other, cheaper models:
  - e.g., use an LLM to configure an expert system or Bayesian classifier, then
    route matching intents to the smaller model.
- Help agent runtimes decide whether pressure belongs to meaning/context,
  policy/runbook, or concrete execution lanes without coupling Automata to one
  provider's internal terminology.
- Seed pressure metrics from long-horizon task readiness, including specs,
  source maps, durable memory, runbooks, checkpoints, audit logs, verification,
  observability, isolated workspaces, repair loops, rollback plans, local
  governance, and tool failures.

### 3.14 Intelligence Hub (Multi-Strategy Insights)

The Intelligence Hub extends the core `Intelligence` orchestrator to:

- Accept **multi-modal inputs** (text, images, audio, video, documents, URLs).
- Split inputs into **segments** with metadata (source, format, context).
- Apply **multiple strategies per segment** and return scored insights
  (not just a single prediction).
- Use `Sensory\Sense` **attention measurements** to determine how shallow or
  deep to analyze each segment.
- Aggregate insights into a **gestalt** view that can be consumed by apps,
  downstream pipelines, or an LLM acting as a coordinator.

The hub is intended to make Automata suitable for "intelligence pipelines"
where a single input (PDF, video, or website) needs to be split, analyzed
by multiple strategies, and recombined into structured results.

### 3.13 Service Layer and Benchmarking

`Service` provides auxiliary services used by strategies and intelligence:

- `BenchmarkService` – wraps training and prediction calls and records
  execution times.

These services support:

- Scoring strategies beyond just accuracy (e.g., latency, cost, stability).
- Operational insights about which strategies are most efficient.

### 3.14 Classification Gateway

The Classification Gateway adds a first-pass labeling system that:

- Routes inputs to classifiers (distinct from predictive strategies).
- Produces a **queryable** `Result` object with:
  - tags/labels + confidence scores,
  - optional proximity/relationship graph between tags,
  - context metadata captured during classification.
- Feeds classification output back into `Engine`/`Intelligence` so that
  strategies can be selected based on categories and content cues, not just
  input type.

Classification operates through:

- `Automata\\Classification\\IClassifier` (train/classify interface).
- `Automata\\Classification\\Gateway` (registry + orchestration).
- `Automata\\Classification\\Graph` for tag relationships.

### 3.15 Initiatives (Goal System)

The Initiatives system is a Holoscene-style, event-driven toolkit for
tracking goals (called "initiatives") with:

- Hierarchical structure (initiatives can contain initiatives).
- Objectives, conditions, KPIs, rewards, prerequisites, tasks, and status.
- Progress rollups that bubble up through the hierarchy.
- Criteria with explicit operators, priorities, and tolerance values.
- Criterion types include `time`, `position`, `item`, and `behavior` (behavior is
  a macro for state/event/action). A `signal` type that bridges Sensory +
  Context is planned but deferred until production use cases are clearer.

Initiatives mirror the prior domain model from the Initiative addon but
replace web-model semantics with intrinsic logic and event hooks.

### 3.16 Feedback, Projections, and Observations

The Feedback system provides:

- `Projection` (expectations/predictions) objects with TTL and priority.
- `Observation` objects representing measured results.
- `Assessor` with pluggable strategies to match observations to projections:
  - time-window matching,
  - label overlap,
  - contextual similarity thresholds.
- Positive/negative feedback signals that can adjust strategy weights or
  initiative progress.

Feedback objects should be serializable, context-aware, and consumable by
both runtime systems and training pipelines.

### 3.17 Media Ingestion and Processing

The Media module provides ingestion and processing utilities for:

- Text, image, audio, video, document, and URL inputs.
- File paths, stream handles, and raw content.
- Normalization, entity extraction, heuristics, and feature engineering.
- Pipelines that feed structured segments into `Intelligence`/`Engine`.

Media is designed to stay lightweight and dependency-free by default.
Advanced capabilities (OCR, ASR, frame extraction) are injected via hooks or
external adapters rather than hard dependencies.

## 4. Typical Usage Patterns

Automata is intended to support workflows like:

1. **Define intents and data types**
   - Describe problem categories (e.g., sentiment analysis, routing, scoring).
   - Map each to input types and candidate strategies.

2. **Register strategies and groups**
   - Implement or configure `IStrategy` implementations.
   - Group them into `DataGroup`s keyed by input type or intent.

3. **Wire inputs and memory**
   - Create `Input` sources (streams, events, HTTP, etc.).
   - Attach input detectors and memory/graph components as needed.

4. **Train and benchmark**
   - Call `Intelligence::train()` with datasets and labels.
   - Inspect scores and adjust selected strategies.

5. **Route production traffic**
   - Call `Intelligence::scan()` / `predict()` for incoming requests.
   - Listen to prediction events and adjust approvals/rejections over time.

6. **Evolve the system**
   - Use game models, genetic algorithms, and ABS/graph-based memory to evolve
     better strategies.

7. **Intelligence Hub workflows**
   - Ingest a complex input (PDF, URL, or media bundle).
   - Segment it by type and metadata.
   - Run multiple strategies per segment, scoring outputs.
   - Combine results into a gestalt summary for downstream decisions.

## 5. Implementation Status and Roadmap (High-Level)

Short‑term priorities:

1. **Collections and core data structures**
   - Harden `Deq`, `Dict`, `List`, `Pile`, `Pri`, and `Vec` with comprehensive,
     performance‑oriented tests.
   - Ensure they are safe and efficient for large data workloads.

2. **Decision trees**
   - Flesh out the `DecisionTree` module with clear methods and documentation.
   - Add full test coverage for at least one complete decision tree pipeline.

3. **Encoding and expert systems**
   - Clarify intent and finalize `Encoding` APIs (text, categorical, numeric,
     etc.).
   - Build strong tests for fact‑based expert systems and extend methods where
     the current implementation is obviously incomplete.

4. **Feature engineering and game theory**
   - Flesh out `Feature` engineering components for common transformations.
   - Expand `GameTheory` into a more complete game modeling system with
     documented examples.

5. **Graph, memory, ABS, and comprehension**
   - Ensure `Path` provides the traversal and manipulation features that
     `Memory` and ABS depend on.
   - Document and test the working-memory semantics of ABS and its relationship
     to Holocene/comprehension systems.

6. **Language and LLMs**
   - Increase test coverage for the `Language` module, especially parser and
     interpreter behavior used by downstream libraries (e.g. Synthetiq).
   - Lock in LLM integration behavior with tests that confirm template and
     parsing expectations.

7. **Collections runtime requirements (ext-ds)**
   - Treat the `ext-ds` PHP extension as a hard requirement for production
     usage of Automata's core collections (`Deq`, `Dict`, `Set`, `Pile`, `Pri`,
     `Vec`) to guarantee performance and semantics.
   - Optionally provide a clearly marked, development-only fallback path (e.g.,
     array-backed polyfills) for environments where `ext-ds` is not available,
     with explicit warnings that behavior and performance may differ.

8. **Examples and documentation**
   - Build an `examples/` directory with runnable examples for each major
     module (collections, strategy, decision trees, expert systems, game
     theory, genetic algorithms, graph/memory/ABS, language, LLM).
   - Favor Develation-style usage (using `Obj`, behaviors, and event-driven
     wiring) so examples double as tutorials for library users and validation
     that the APIs are idiomatic.

9. **Classification gateway**
   - Provide a unified classification gateway with queryable results.
   - Introduce tag graphs and metadata-aware classification.
   - Expose events for downstream selection and routing.

10. **Initiatives (goal trees)**
   - Port initiative domain concepts (initiative, objective, condition, KPI,
     reward, prerequisite, task, status, types).
   - Provide hierarchical progress rollups and sibling awareness.
   - Ensure goals can emit projections for feedback assessment.

11. **Feedback loop**
   - Implement projection/observation/assessor with multiple matching
     strategies and TTL handling.
   - Provide feedback signals and a registry for positive/negative weighting.
   - Make feedback handlers opt-in for strategies and context objects.

12. **Anomaly detection toolkit**
   - Provide a multi-detector anomaly gateway with activity/fingerprint
     workflows and context-aware scoring.
   - Add example scripts for fraud-style flows (device/location fingerprints,
     behavior deltas) and baseline tests for detectors.

13. **Media ingestion and processing**
   - Add ingestion gateways and media pipelines for text, image, audio, video.
   - Document pipeline extension points for OCR/ASR/frame analysis.
   - Provide examples wired into Intelligence for multi-modal inputs.

14. **Demos**
   - Disaster response classification demo (mock dataset first, real dataset
     later) that exercises classification + feedback.
   - An agent demo that runs without LLM keys and can optionally use LLM
     strategies when keys are provided.

Future directions:

- Add pluggable connectors for major cloud AI providers (SageMaker, Bedrock,
  Azure, etc.).
- Standardize strategy "capability descriptions" so that higher-level code can
  discover suitable strategies at runtime.
- Introduce more sophisticated scoring functions that combine accuracy,
  latency, cost, and stability over time.
- Decouple php-ml behind model interfaces/wrappers and allow alternative ML
  backends (Rubix, optional Python bridges) without changing Automata APIs.
- Add injected assessors and adapter-aware utility hooks for modules that
  benefit from worldview state without forcing broad cross-module coupling.

## 6. Example Specifications

### 6.1 Classification Gateway (Disaster Response)

**Goal:** Classify incoming media metadata and text descriptors into tags such
as `damage`, `people`, `infrastructure`, `blocked_road`, `flooding`.

**Inputs:**
- Mock dataset of labeled items (image metadata + short text notes).
- Features include: mime type, dimensions, file size, color profile, and
  normalized keyword counts.

**Output:**
- `Result` with tags and confidence scores.
- Optional tag graph linking related labels (e.g., `flooding` <-> `road`).

### 6.2 Initiatives (Goal Trees)

**Goal:** Define an initiative tree for disaster response:
- `Disaster Response` (root)
  - `Infrastructure Recovery` (child)
  - `People Safety` (child)

**Criteria:**
- Objectives (KPIs) like `roads_cleared >= 80%`, `medical_supply >= 60%`.
- Conditions like `power_grid_status is stable`.

**Output:**
- Initiative progress rollups.
- Projections generated from unsatisfied objectives.

### 6.3 Feedback Loop

**Goal:** Assess observations (incoming field reports) against projections.

**Matching Strategies:**
- Label overlap between observation tags and projection tags.
- Time-window match for projections with TTL.
- Context similarity thresholds (region, priority, event).

**Output:**
- Positive/negative feedback signals.
- Updated strategy weights or initiative progress.

### 6.4 Overarching Demo (Agent-Ready)

**Goal:** Run the classification + initiative + feedback loop end-to-end.

**Requirements:**
- Runs without LLM keys using mock classifiers/strategies.
- Optional LLM-backed strategies activated when keys are provided.
- Logs assessments and feedback signals for inspection.

**Future extension:**
- A more complex agent demo that uses feedback to evolve behaviors over time.

This SPEC is intended to be a living document; as Automata evolves, new modules,
strategies, and integrations should be added here alongside their intended use
cases and constraints.

## 7. Experiential learning foundation

The `Learning` namespace captures normalized `Statement` and `Context` snapshots,
observed outcomes, and strategy-specific training projections. It does not grant
execution authority or automatically train or replace a live strategy.

User stories and acceptance criteria:

- A host records a situated observation without later mutation of its Statement,
  Context, or array references changing the stored experience.
- A reviewer can attach a delayed outcome to its exact experience. Repeated
  identical outcomes are idempotent; conflicting ids or wrong lineage are rejected.
- A training adapter projects only suitable evidence into samples and labels.
  Recomposition rejects references to outcomes absent from the source experience.
- A host can round-trip schema-versioned records and preserve context data, tags,
  normalizations, provenance, trace id and outcome attribution. Unsupported schema
  versions and runtime objects fail explicitly.
- The provider-free Cortex example records episodes in Holoscene, excludes pending
  reviews from training, and evaluates the existing Naive Bayes strategy against
  independent synthetic fixtures. Its process exit code reflects conformance.

The reference store is process-local. Durable concurrent storage, access controls,
and recovery remain open. The following sections specify the staged learning,
activation and response capabilities. See [the delivery plan](docs/cortex-delivery.md) and
[the runnable example](examples/generic/cortex/README.md).

## 8. Held-out classification evaluation

Compare separately instantiated, already trained models using exact identities and
the same versioned projection. Reject overlap with either declared training corpus
and repeated holdout evidence before invoking prediction. Measure strict label
matches, failures and elapsed milliseconds; retain unknown cost/energy as unknown.
Recommend only strict improvement meeting sample, accuracy and optional latency
policy. Ties, regressions and unreliable evidence retain the incumbent. Evaluation
must not train, save, replace or authorize a strategy. The runnable experiment
must demonstrate both an improving candidate and a rejected regression.

This stage does not establish statistical significance, detect undisclosed model
training, cancel synchronous prediction, or implement promotion/rollback. Those
limits remain explicit while the complete learning loop advances.

## 9. Attributed strategy feedback

Admitted outcomes can update advisory Intelligence performance for an explicitly
named strategy version and context. The bridge requires attached evidence, rejects
malformed/ambiguous attribution and invalid metrics, omits unknown measurements,
and records inspectable receipts. Identical evidence is applied once per recorder
instance; conflicting evidence and uncertain partial-application retries fail
explicitly. Outcomes cannot add candidates, change eligibility or grant authority.

The Cortex proof must evaluate a classifier, apply attributed held-out observations,
show changed future route preference, and retain exact-version, eligibility,
authorization and resource-limit checks. Replay guarantees are process-local;
durable, transactional feedback remains subsequent work. Model activation is a
separate operation specified below.

## 10. Progressive response composition

Response envelopes declare fixed fragment identities, channels, weights, blocking
requirements and dependencies before work. Independently produced fragments may
report progress and resolve through fluent APIs. A configured threshold cannot
fall below its policy floor, and no threshold bypasses a failed or incomplete
blocking requirement. Confirmation fragments wait for successful receiver receipts
for their dependencies; a prepared command alone is not evidence of success.

Releases have stable identities and explicit per-fragment completion receipts.
Data-only checkpoints preserve pending and acknowledged delivery state. Hosts own
trusted checkpoint storage, receiver idempotency and actual effect authorization.
Cancellation stops new output and late producers while retaining in-flight evidence
for reconciliation. Predeclared nonblocking fallback content cannot prove the
original operation succeeded. The demo must show progressive output, a lost-ack
restart, a single simulated effect, failure/fallback and cancellation.

Agent integration is specified below. Concurrent transactional persistence remains
subsequent work; the generic composer does not execute effects.

## 11. Agent response integration

An Agent can start or restore a response handle bound to its session and task.
The handle adapts explicit completed/failed worker results into fragments and can
wrap workers for the existing orchestrator. Unknown confidence stays unknown.
Cancelled, duplicate, invalid or differently scoped work cannot invoke a producer.
TaskTrace records production, release, acknowledgement and cancellation separately;
observational telemetry failure cannot repeat work or erase committed response state.
Checkpoints restore between producer calls and reject a different session or task.
Host authorization, tenant/actor identity, receiver evidence and durable storage
remain separate requirements. A runnable proof must use real governed Agent tools,
show denied execution and revoked permission, and delay confirmation until receipts.

## 12. Controlled model activation

A process-local lifecycle owns the active candidate reference and a monotonic
revision. Promotion evaluates the exact candidate against the current incumbent
before asking a trusted host for an explicit approved GovernanceDecision. Pending,
denied or steered decisions cannot activate a model. Rollback separately authorizes
return to the previous instance. Requests bind an id, expected revision and exact
model/evidence; identical retries return historical receipts without new effects.
Conflicting retries, stale revisions, reused versions, reentrant mutations and full
retention bounds fail before invoking models or host callbacks. The host must keep
model instances immutable throughout their registered lifetime.

The synthetic proof must freeze its corpus and expected labels, detect constant
and single-wrong predictions, demonstrate activation changing actual predictions,
reject a regression, then restore the previous model. This is not durable model
deployment, concurrent coordination, permission to execute tools or production
quality certification.

## 13. Evidence-triggered candidate training

A learning coordinator evaluates bounded projected evidence against retained
lineage from every successfully trained batch. New evidence is counted by exact experience/outcome
lineage; duplicate rows and conflicting historical reuse are rejected. Explicit
correction references must identify new rows in the current batch. Host-normalized
pressure and operator requests can request training but cannot bypass the minimum
sample floor, maximum batch size or explicit host approval.

Training constructs an isolated strategy and invokes a trusted trainer bridge.
It never trains the incumbent or activates a candidate. Results retain exact
identity, policy assessment, evidence digest and approval. Identical request replay
returns the original result without callbacks; a partial factory/trainer failure
is uncertain and reserves its version against blind retry. Request retention is
bounded and process-local. Callbacks own real resource enforcement and model-state
isolation; synchronous elapsed time is measurement, not cancellation.

The integrated proof must record experience, defer insufficient evidence, deny
unapproved training, train a classifier, evaluate and separately approve activation,
then change a later Agent plan while tool approval and terminal delivery receipts
remain independently required. Rollback must change subsequent plans back.

## 14. Composed strategy workflows

Versioned workflow proposals reuse Path graph nodes and edges. CompositeStrategy
implements IStrategy and is selected through existing Intelligence prediction.
Runs must enforce exact strategy/capability identity and current node authorization
through StrategyRouter, supporting conditional dependencies, all/any joins,
explicit known-failure fallback, bounded retries and output completion thresholds.
Independent workers can overlap under a host scheduler; cancellation and early
completion stop new dispatch while preserving in-flight observations. Unknown
execution stops automatic fallback/retry. Plans and results are plain records,
not authority or authenticated resumable workers. Durable scheduling, global
resource reservations, nested budget accounting and route training remain open.
