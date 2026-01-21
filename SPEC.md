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

- `GraphTheory` – traversal, pathfinding, similarity, and structural operations.
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

### 3.9 Genetic Algorithms

The `Genetic` module:

- Evolves candidate solutions via fitness functions and simple genetic
  operators.
- Should make it easy to plug in new fitness functions and solution
  representations.

It is a natural complement to:

- Strategy selection (evolving ensembles).
- Hyperparameter search for other models.

### 3.10 Analysis, Classification, and Decision Trees

The `Analysis` folder is focused on **classification**:

- Current classifiers are opinionated; the goal is to add more generic,
  reusable classifiers with clear interfaces.
- Support for building ensembles from multiple classifiers.

The `DecisionTree` folder:

- Represents low‑hanging fruit for early, complete testing.
- Needs more methods, clearer intent, and documentation for each method.
- Should provide a canonical, well‑tested implementation of decision trees in
  the library.

### 3.11 LLM and External Model Integration

The `LLM` module integrates:

- Template and parsing systems (via Develation and other libraries).
- HTTP clients and remote APIs (e.g., OpenAI, Gemini).

Intent:

- Provide a **structured interface** around LLM calls.
- Allow LLMs to configure and supervise other, cheaper models:
  - e.g., use an LLM to configure an expert system or Bayesian classifier, then
    route matching intents to the smaller model.

### 3.12 Intelligence Hub (Multi-Strategy Insights)

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
   - Ensure `GraphTheory` provides the traversal and manipulation features that
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

12. **Demos**
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
