# BlueFission Automata – Architecture Overview

## 1. High‑Level Architecture

At a high level, Automata sits between:

- **Host applications** (SaaS apps, services, agents, CLI tools, etc.)
- **Develation** (object model, behaviors, templates, parsing)
- **External AI systems** (LLMs, cloud ML, classical ML libraries, etc.)

```text
Host App
  |
  v
Automata (Intelligence, Strategies, Sensory, Memory, Language, Services)
  |
  v
Develation (Obj, behaviors, templates, collections)
  |
  v
External AI (OpenAI, Claude, SageMaker, php-ml, custom models)
```

Automata:

- Provides **opinionated AI building blocks** and orchestration.
- Relies on Develation for base behaviors and infrastructure.
- Treats external AI systems as interchangeable backends behind strategy
  interfaces.

## 2. Core Layering

### 2.1 Core Orchestration Layer

**Key modules:**

- `Automata\Intelligence`
- `Automata\Context`
- `Automata\InputType` / `InputTypeDetector`
- `Automata\DataGroup`

Responsibilities:

- Register, group, and score strategies.
- Detect input type / intent and route to appropriate strategy group.
- Dispatch events when predictions are made (`PREDICTION_EVENT`).
- Manage feedback loops (approve/reject predictions).

**Intelligence Hub extension**

The Intelligence Hub builds on `Automata\Intelligence` by:

- Segmenting multi-modal inputs into typed slices with metadata.
- Running multiple strategies per segment (not just a single best strategy).
- Using `Sensory\Sense` attention metrics to set analysis depth and
  strategy budget.
- Aggregating outputs into structured insights plus a gestalt summary
  suitable for an application or LLM coordinator.

### 2.2 Strategy Layer

**Key modules:**

- `Automata\Strategy` (interfaces and concrete strategies)
- `Automata\Service\BenchmarkService`

Responsibilities:

- Implement `IStrategy` for various model families:
  - statistical (Bayes, n‑grams),
  - classical ML (k‑NN, decision trees),
  - neural (image classifiers, etc.),
  - orchestration strategies (e.g., meta‑strategies that delegate further).
- Benchmark training and prediction, returning both outputs and timing.
- Expose clean, minimal interfaces that do not leak backend details.

### 2.3 Sensory and Collections Layer

**Key modules:**

- `Automata\Sensory` (Input and related classes)
- Core data structures: `Deq`, `Dict`, `List`, `Pile`, `Pri`, `Vec`
- `Automata\Collections` (e.g., `OrganizedCollection`)

Responsibilities:

- Ingest raw signals and normalize them for strategies.
- Provide efficient, AI‑focused data containers for large and/or streaming
  workloads.
- Maintain ordering, priority, and weighting semantics where needed.

### 2.4 Analysis, Expert, Feature, Game, Genetic, and Graph Layers

**Key folders:**

- `Analysis` – classification and meta‑analysis.
- `Expert` – rule/fact‑based expert systems.
- `Feature` – feature engineering and transformation.
- `GameTheory` – game models, players, and strategies.
- `Genetic` – genetic algorithms and fitness‑driven search.
- `GraphTheory` – graph structures and traversal algorithms.

Responsibilities:

- Provide specialized tools that can be wrapped as `IStrategy` where
  appropriate.
- Offer reusable building blocks for classification, inference, search, and
  simulation.
- Contribute data structures and algorithms that tie directly into memory and
  comprehension.

### 2.5 Memory, ABS, and Comprehension Layer

**Key folders:**

- `Memory`
- (Related: `GraphTheory`, `Comprehension`, Holocene‑related modules)

Responsibilities:

- Represent concepts and experiences as nodes and edges in graph structures.
- Implement ABS (Absolute/Abstract) concept mapping:
  - create a vector‑like space of concepts,
  - support similarity search and generalization over experiences,
  - maintain a bounded working memory focused on current problem solving.
- Coordinate with comprehension systems (e.g., Holocene) to:
  - traverse experiences,
  - recover relevant context,
  - update knowledge graphs as events occur.

### 2.6 Language and LLM Layer

**Key folders:**

- `Language`
- `LLM`
- (Related Develation templating and parsing facilities)

Responsibilities:

- Provide a rule‑based NLP system:
  - tokenization,
  - grammar,
  - interpretation,
  - document modeling.
- Offer a simple, consistent interface for LLM‑backed operations:
  - prompt templating and rendering,
  - request formatting,
  - response interpretation and routing.
- Bridge between symbolic NLP (Language) and sub‑symbolic systems (LLMs and
  embeddings).

## 3. Representative Data Flows

### 3.1 Intent Classification and Routing

1. Host app constructs an `Input` instance and passes it to `Intelligence`.
2. `InputTypeDetector` infers the type (e.g., text vs. numeric vs. graph).
3. `Intelligence` selects the appropriate `DataGroup` for that type.
4. Strategies in the group are invoked (possibly via `BenchmarkService`).
5. The best performing strategy’s result is emitted via `PREDICTION_EVENT`.
6. A downstream router uses that event to:
   - send requests to a cheap specialist model (e.g. a Bayes classifier),
   - or escalate to a large LLM only when necessary.

This allows Automata to behave like a **controller/router layer** for AI
problems, similar to HTTP controllers for web routes.

### 3.2 Language Understanding with Memory

1. Text input arrives via `Sensory\Input`.
2. `Language` components tokenize and parse the text into an internal
   representation.
3. The parsed representation is mapped to concepts in `Memory` / ABS:
   - new nodes and edges are created or updated,
   - similarity to existing experiences is computed.
4. Strategies consult both:
   - the immediate language parse,
   - relevant nodes in working memory,
   to produce predictions or actions.
5. Experiences are appended to Holocene/comprehension structures, enabling
   future traversals and learning.

### 3.3 Game Simulation and Strategy Evaluation

1. A game is defined in `GameTheory`:
   - state representation,
   - rules and transitions,
   - players and utilities.
2. Strategies (possibly genetic or RL‑style) interact with the game:
   - play multiple episodes,
   - explore strategy space.
3. `BenchmarkService` measures performance across episodes.
4. `Intelligence` integrates results:
   - updates weights for game strategies,
   - prefers those that perform better for specific intents or conditions.

### 3.4 Genetic Optimization of Strategy Ensembles

1. An ensemble of strategies is encoded as a genome (e.g., selection and
   weighting of models).
2. The genetic module produces variations and evaluates them:
   - uses `BenchmarkService` and test datasets,
   - measures both accuracy and resource usage.
3. Over generations, better ensembles emerge.
4. The best genome is converted back into a set of `DataGroup` configurations
   that `Intelligence` can use in production.

### 3.5 Intelligence Hub: Multi-Modal Insight Pipeline

1. A host app submits a complex input (PDF, URL, video, or mixed media bundle).
2. The hub segments the input into slices (text, image, audio, video) and
   attaches metadata like source, format, and provenance.
3. `Sensory\Sense` evaluates attention and novelty to determine depth/budget.
4. A ranked list of strategies is applied per segment; outputs are scored.
5. Insights are aggregated into a gestalt summary for downstream systems,
   including LLM-driven orchestration.

## 4. Module Status and Architectural Priorities

Short‑term architectural focus areas:

- **Collections Layer**
  - Confirm that custom collections (`Deq`, `Dict`, `List`, `Pile`, `Pri`,
    `Vec`, `OrganizedCollection`) meet performance and ergonomics needs.
  - Treat these as “infrastructure” that higher‑level modules rely on heavily.

- **DecisionTree and Analysis**
  - Fill out missing methods and document each step of the decision tree
    lifecycle (construction, training, prediction, introspection).
  - Establish a canonical decision tree implementation with full tests.

- **Encoding and Expert**
  - Clarify how different encoding schemes feed into expert and strategy
    modules.
  - Stabilize expert system APIs before widespread reuse.

- **Graph, Memory, ABS, Comprehension**
  - Make sure graph operations exposed by `GraphTheory` are sufficient for
    memory and ABS use cases (e.g. nearest‑neighbor search, path queries).
  - Document the contract between Holocene/comprehension modules and memory.

- **Language and LLM**
  - Protect backward‑compatible behavior for components that are consumed by
    other libraries (e.g. Synthetiq).
  - Clearly separate:
    - rule‑based language infrastructure,
    - LLM/remote‑model orchestration.

## 5. Extensibility Guidelines

When adding new modules or integrating new AI systems:

- Prefer **interfaces and behavioral contracts** over concrete types.
- Keep new code **Develation‑aware**:
  - use `Obj` and behaviors where appropriate,
  - emit events instead of baking in strong coupling.
- Ensure strategies and services can be:
  - registered with `Intelligence`,
  - benchmarked and compared,
  - swapped out without changing calling code.
- Keep Automata focused on orchestration and composition; offload heavy math
  and backend‑specific details to specialized libraries where possible.

This architecture document should be read together with `SPEC.md`, which
describes product intent and roadmap. Architectural changes should update both
files as modules evolve.

