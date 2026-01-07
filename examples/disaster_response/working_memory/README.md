# Working Memory ABS2 Example

This example demonstrates how to use Automata's ABS/working-memory implementation (`Abs2Memory`) in the context of the disaster-response logistics domain.

## What it demonstrates

- Storing episodic experiences as graph nodes with `Context` payloads.
- Creating associations between related episodes (e.g., same hospital, different conditions).
- Performing similarity-based recall for a new request.
- Inspecting a simple association path between two episodes.

## Why this model fits

For disaster-response planning, we often need to answer:

- “What happened last time we tried something like this?”
- “Which past deliveries are most similar to the current situation?”

`Abs2Memory` provides a small, explainable working-memory graph that can answer these questions without over-specializing the core library.

## How to run

From the project root:

```bash
php examples/disaster_response/working_memory/run.php --seed=123
```

## Inputs

- Seeded randomness via `--seed=INT` (currently used only for determinism).
- Hard-coded episodic data representing a few delivery attempts.

## Outputs

- JSON log to stdout, containing:
  - `seed`: the seed used.
  - `query`: the synthetic request context.
  - `similar`: similar episodes with their contexts and similarity scores.
  - `path_example`: an association path through the memory graph.

This log is suitable for future dashboarding or automated regression checks.

