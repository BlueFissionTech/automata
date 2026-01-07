# Comprehension, Intelligence, Engine, and Sensory — Architectural Notes

This document captures the intent and coupling between the core subsystems that model _experience_, _attention_, and _strategy orchestration_ in Automata. It is based on the current code, existing comments (including the more “esoteric” ones), and inferred design goals.

The goal is to preserve the psychological “feel” of these components while making their relationships explicit and testable.

## 1. Comprehension — Episodic Experience

### Frame

File: `src/Automata/Comprehension/Frame.php`

- A `Frame` represents a **single moment of experience**.
- It aggregates contributions from multiple “experiences” (sources), each of which exposes a `values` array:
  - `['label' => ['value' => ..., 'weight' => ...], ...]`
- Key behavior:
  - `addExperience($experience, $source)`:
    - Stores an experience snapshot keyed by its source (sensor, strategy, etc.).
  - `process()`:
    - Collects `values` across all experiences.
    - Uses `OrganizedCollection` to combine and reweight observations of the same label.
    - Produces an ordered, weighted view of “what mattered” in this frame.
  - `extract()`:
    - Returns a truncated subset of the top-weighted values from each experience.
    - Used by `Scene` to build `Context` objects for entities in the scene.
  - `hashArray()`:
    - Converts frame values into a fixed-length numeric vector using `crc32`-based hashing.
    - Intended as a simple “embedding” for similarity and clustering, without heavy deep models.

### Scene

File: `src/Automata/Comprehension/Scene.php`

- A `Scene` manages a **short history of Frames** and converts them into:
  - `Context` objects per entity/label.
  - Clusters (groups) of related contexts.
  - Temporal edges linking related contexts across time.
  - Entries in a working memory (`IWorkingMemory`), typically `Abs2Memory`.
- Key concepts:
  - `_frames`:
    - A buffer of recent `Frame` instances (bounded by `_buffer_size`).
  - `_groups`:
    - Clusters of contexts, representing conceptual “mobs” or grouped concepts.
  - `_temporalEdges`:
    - `TemporalEdge` objects connecting related contexts, with reinforcement and decay.
  - `_memory`:
    - An `IWorkingMemory` implementation; this is where episodic contexts are stored as graph nodes.
- Flow:
  - `addFrame(Frame $frame)`:
    - Maintains a window of recent frames.
    - Calls `extract()` to obtain per-entity data.
    - Passes entities into `processEntities()`.
  - `processEntities(array $entities)`:
    - For each entity/label, builds a `Context`:
      - `label`, `value`, `weight` (and optional meta).
    - Assembles a map of `[label => Context]`.
    - Clusters those contexts (similarity-based) into groups.
    - For each cluster:
      - If size 1 → store the single context directly in working memory.
      - If size > 1:
        - Combine member contexts into a group context.
        - Create a group label via `generateGroupLabel()`.
        - Store the group in working memory and track the member contexts in `_groups`.
        - Update temporal edges between cluster members to reflect co-occurrence and recency.
  - `recallFromGroup(string $groupLabel, float $tolerance, ?IRecallScoringStrategy $strategy)`:
    - For a given group, scores similarity between each pair of member contexts:
      - Uses either a provided `IRecallScoringStrategy` or cosine similarity over hashed context vectors.
    - Returns contexts whose similarity is above the tolerance.
    - This enables **within-group episodic recall** (“call back the details of this situation”).

### Holoscene

File: `src/Automata/Comprehension/Holoscene.php`

- Inspired by Gestalt psychology:
  - Proximity, similarity, continuity, pragnanz, symmetry, closure, common fate.
- Intended to represent a **higher-level “map of scenes”**:
  - Scenes are aggregated and assessed as a cohesive “holoscene” (holistic scene).
  - Able to score and compare scenes using `OrganizedCollection` statistics.
- Design goals:
  - Collect multiple Scenes or Frames keyed by input or scenario.
  - Run statistics over the scenes to compute a global `_assessment`.
  - Provide a way to export “experience models” for:
    - Games/simulations (e.g. injecting historical episodes into scenarios).
    - Expert systems (turning observed patterns into facts and rules).
- The current code is incomplete, but the comments and structure show the intention:
  - A Holoscene is a **narrative-level container for Scenes** that can be queried and assessed.

### Log

File: `src/Automata/Comprehension/Log.php`

- `Log` turns a scene/episode into a **structured narrative card**:
  - Sections: `##Scene`, `##Context`, `##Tags`, `##Characters`, `##Facts`, `##Description`.
- Encodes:
  - Entities (via `addEntity()` → `Entity` objects).
  - Facts (via `addFact()`).
  - Tags (via `addTag()`).
  - Time and place.
- Intended uses:
  - Human-readable “microfiche” of an experience.
  - Export target for Holoscene snapshots.
  - Input to games/simulations or expert systems (e.g., convert facts into rules).

## 2. Working Memory and ABS2 Map

The `Memory` subsystem (especially `Abs2Memory`, `MemoryNode`, and recall strategies) provides a **semantic graph for working memory**:

- Nodes:
  - `MemoryNode` representing experiences or concepts.
  - Each node holds a `Context` (labels, values, weights, timestamps, etc.).
- Edges:
  - Weighted associations between node names.
  - Used for shortest paths and association-based traversal.
- Similarity:
  - Node-level similarity (`MemoryNode::similarity`) on `Context`.
  - Pluggable strategies (`CosineSimilarityStrategy`, `SemanticDistanceStrategy`, `TemporalDecaySimilarityStrategy`, etc.).

Coupling with Comprehension:

- `Scene` writes contexts and grouped contexts into `Abs2Memory` via `IWorkingMemory`:
  - Single-entity memories.
  - Group-level memories (clusters).
- `Abs2Memory` then:
  - Supports context-based recall (`recallSimilar`).
  - Supports structured traversal (`shortestAssociation`, `reinforcePath`).

Together, Comprehension + Working Memory create a **narrative, traversable memory**:

- Similar to RAG, but:
  - Built on structured `Context` and graph paths.
  - Designed for “rewind” and “fast-forward” through episodic chains.

## 3. Intelligence and Strategy Orchestration

### Intelligence

File: `src/Automata/Intelligence.php`

- Manages a portfolio of `IStrategy` implementations (e.g., KNN, Naive Bayes, decision trees).
- Responsibilities:
  - Register strategies and strategy groups (by data type).
  - Train strategies, measure accuracy and execution time.
  - Maintain weights (scores) in an `OrganizedCollection`.
  - Select the best strategy for prediction.
- Key behaviors:
  - `train($dataset, $labels)`:
    - Benchmarks training with `BenchmarkService`.
    - Uses `accuracy()` and execution time to compute a score.
    - Updates weights in `_strategies`.
  - `predict($input)`:
    - Uses the top-weighted strategy to predict.
    - Records the last strategy used for feedback.
  - `approvePrediction()` / `rejectPrediction()`:
    - Adjust weights of the last used strategy based on feedback.
  - `scan($input)`:
    - Determines the data type.
    - Uses appropriate strategy group (`DataGroup`) to benchmark predictions and dispatch events.

### Engine

File: `src/Automata/Engine.php`

- Extends `Intelligence` and adds:
  - Service and permission models.
  - Transaction size and performance tracking.
  - Hooks into sensory inputs and strategies.
- Conceptually a **hyper-integrated orchestrator**:
  - Receives sensory input.
  - Routes it through strategies and services.
  - Logs and scores behavior over time.
  - Intended to tie directly into Comprehension (Frames/Scenes) and Working Memory.

## 4. Sensory: Input and Sense

### Input

File: `src/Automata/Sensory/Input.php`

- Provides an event-driven pipeline for raw data:
  - Holds a chain of processors (callables).
  - `scan($data)` applies them in sequence.
  - Dispatches `Event::COMPLETE` with processed data.
- Used by `Intelligence::registerInput()` to hook prediction logic onto input completion events.

### Sense

File: `src/Automata/Sensory/Sense.php`

- Models **attention and novelty** over input streams.
- Key configuration (`_config`):
  - `attention`: total “budget” for how much to inspect.
  - `sensitivity`: how many passes/depth levels.
  - `quality`: sample rate for input.
  - `tolerance`: difference tolerance between chunks.
  - `dimensions`: how many dimensions to consider for mapping.
  - `features`, `flags`, `chunksize`.
- Behavior:
  - `_preparation`:
    - At depth 0, uses `Language\Preparer` to tokenize text.
    - At deeper levels, creates sliding windows over the input string.
  - `invoke($input)`:
    - Prepares and builds an internal matrix of chunks.
    - Iterates across attention and sensitivity:
      - Uses `_map` (OrganizedCollection) to track chunk weights and stats.
      - Adjusts `attention` upward when new chunks are seen (novelty).
      - Adjusts `quality` based on novelty vs repetition.
    - Contains comments indicating the long-term intent:
      - Translate chunks via associative indexes.
      - Track novelty and boredom.
      - Build maps for regression/classification.
      - “Create a ‘holoscene’ by association of correlated points.”

Sense is a **psychologically-inspired filter** that decides how deeply to look at input and what gets prioritized.

## 5. How They Work Together (Model of Mind)

Putting it together:

1. **Sensing & Preprocessing**
   - `Input` and `Sense` receive raw data (text, signals, sensor streams).
   - Optional Encoding/Feature/Normalization transform it into structured vectors.

2. **Strategies & Intelligence**
   - `Intelligence` maintains multiple strategies and their scores.
   - It selects strategies to:
     - Classify.
     - Predict.
     - Cluster.
     - Navigate decisions.
   - Feedback keeps strategy weights fluid at runtime, enabling replacement or deprecation of outdated models.

3. **Comprehension & Working Memory**
   - `Frame` captures per-moment experiences from sensors and strategies.
   - `Scene` aggregates frames into clusters and stores contexts in working memory (`Abs2Memory`).
   - Temporal edges connect related contexts across time.
   - Working memory enables:
     - Contextual recall (similarity).
     - Path-based navigation (rewind/fast-forward through episodes).

4. **Holoscene & Logs**
   - `Holoscene` aggregates scenes and assesses them with Gestalt-inspired criteria.
   - `Log` turns selected scenes into narrative “microfiche”:
     - Useful for human review.
     - Exportable to games/simulations or expert systems.

5. **LLM Layer (optional, not required)**
   - LLM clients and Agent/FillIn can sit on top:
     - To classify tasks.
     - To choose strategies.
     - To summarize holoscenes or logs.
   - They are **not required** for the core flow; strategies, Comprehension, and working memory can operate independently.

This architecture is deliberately flexible:

- Each subsystem (Comprehension, Intelligence, Engine, Sensory, Memory) is usable on its own.
- When coupled, they form a “model-of-mind” for:
  - Recording, traversing, and narrating experience.
  - Choosing and evolving strategies.
  - Offloading work from deep models to cheaper, faster, explainable components.

