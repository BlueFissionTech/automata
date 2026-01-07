# Automata Working Memory (ABS2)

This document describes the intent and usage of the `Memory` / `ABS2` subsystem in the Automata library.

## Purpose

The working-memory layer is designed to provide an **absolute / abstract (ABS)** concept map for AI agents:

- **Nodes** represent experiences or concepts (`MemoryNode`).
- **Edges** represent associations or transitions between those experiences.
- Each node carries a structured `Context` payload describing what was true at that moment.
- The graph exposes both **path-based** (graph) and **content-based** (similarity) recall.

This is intended to complement LLMs and other models by providing a compact, explainable working memory that can be queried and updated over time, instead of relying solely on transient prompt context.

## Key Types

- `BlueFission\Automata\Context`
  - Lightweight key–value store for episodic data (features, tags, timestamps, scores, etc.).
- `BlueFission\Automata\Memory\MemoryNode`
  - Extends `GraphTheory\Node` and wraps a `Context` instance.
  - Provides `reinforce(float $amount)` to strengthen usage.
  - Provides `similarity(Context $other)` for coarse similarity between contexts.
- `BlueFission\Automata\Memory\Abs2Memory`
  - Implements `IWorkingMemory` on top of `GraphTheory\Graph` and an `OrganizedCollection`.
  - Responsible for adding memories, linking them, recalling by label, association, or similarity.
- Recall scoring strategies:
  - `CosineSimilarityStrategy`
  - `WeightedCosineSimilarityStrategy`
  - `SemanticDistanceStrategy`
  - `TemporalDecaySimilarityStrategy`
  - `LevenshteinLabelSimilarityStrategy`

## IWorkingMemory contract

`IWorkingMemory` defines the surface API for working-memory engines:

- `addMemory(string $label, Context $context, array $edges = [])`
- `getMemory(string $label): ?MemoryNode`
- `reinforcePath(string $start, string $end): array`
- `contextSwitchPath(string $from, string $to): array`
- `recall(string $label): ?Context`
- `recallWithAssociations(string $label, int $max = 10): array`
- `associate(string $name1, string $name2, float $weight = 1.0): void`
- `shortestAssociation(string $start, string $end): array`
- `forget(string $name): void`
- `contents(): array`

`Abs2Memory` is the primary implementation and should be used wherever a graph-like ABS working memory is required.

## Graph vs Context

- **Graph side** (via `GraphTheory\Graph`):
  - Handles shortest paths and structural relationships between memories.
  - Useful for "how do I get from situation A to situation B via known experiences?"
- **Context side** (via `Context` and `MemoryNode::similarity`):
  - Handles feature-based or text-based similarity between episodes.
  - Useful for "what past situations *feel* most like this one?"

In practice you can:

- Use `shortestAssociation` and `reinforcePath` for structural reasoning and reinforcement.
- Use `recallSimilar` (and/or pluggable scoring strategies) for content-driven retrieval.

## Disaster-response usage (example domain)

In the disaster-response examples:

- Each memory node can represent a **logistics episode**, such as "delivered insulin to Hospital-A during level-3 flooding, route via Bridge-12, arrival late".
- The `Context` for that node might include:
  - `location`, `asset_type`, `risk_level`, `success`, `delay_minutes`, `timestamp`.
- Edges represent conceptual or temporal links between episodes (similar route, same hospital, same weather regime, etc.).
- When a new request arrives, an agent can:
  - Build a `Context` for the new situation.
  - Call `recallSimilar` to fetch the most relevant past episodes.
  - Optionally walk or reinforce association paths after selecting a plan.

This gives LLM and GOFAI strategies grounded, explainable examples, without over-specializing the core library itself.

