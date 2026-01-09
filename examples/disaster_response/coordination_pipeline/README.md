# Coordination Pipeline (Sense + Data Science + Intelligence)

## What this demonstrates

- Using `BlueFission\Automata\Sensory\Sense` to break down multi-organization event descriptions.
- Applying normalization and encoding (`NumericalScaler`, `CategoricalEncoder`) as lightweight data science helpers.
- Routing feature vectors through `BlueFission\Automata\Intelligence` with a simple `IStrategy` implementation.
- Recording events into `Frame` / `Scene` with `Abs2Memory`, then wrapping the episode in `Holoscene` and narrating it with `Log`.

The domain here is coastal county disaster response: coordinating between EOC, hospitals, and shelters for road closures, supply runs, and capacity alerts.

## Why this model fits

- Sense provides an attention-like way to tokenize and chunk incoming descriptions.
- Normalization/encoding give generic, reusable feature vectors for downstream strategies.
- `Intelligence` centralizes strategy selection and prediction, matching Automata’s design.
- Comprehension + Memory (`Frame`, `Scene`, `Holoscene`, `Abs2Memory`) turn a stream of events into an episodic record that can be recalled or summarized later.

## How to run

From the project root:

```bash
php examples/disaster_response/coordination_pipeline/run.php
```

This script has no external API dependencies and should run anywhere DevElation and the Automata library are available.

## Inputs and outputs

- **Inputs**
  - Hard-coded synthetic events describing:
    - Road closures
    - Hospital supply requests
    - Shelter capacity issues
- **Internal processing**
  - `Sense` tokenizes descriptions.
  - `NumericalScaler` scales severity and chunk count.
  - `CategoricalEncoder` one-hot encodes organization + event type.
  - `Intelligence` + `CoordinationPriorityStrategy` assign a coarse priority label (`critical` / `normal`).
  - `Frame` / `Scene` write structured context into `Abs2Memory`.
  - `Holoscene` stores the scene as an episode; `Log` composes a Markdown narrative.
- **Outputs**
  - JSON summary of events and predicted priorities.
  - A Markdown-like narrative describing the coordination episode.

## What to look for

- Each event should have:
  - A `chunks` field from `Sense`.
  - A corresponding entry in the `predictions` map.
- The narrative should mention:
  - The EOC location (“Coastal County EOC”).
  - Characters/entities for each organization.
  - Facts summarizing the road, hospital, and shelter events.

