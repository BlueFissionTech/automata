# Social Media Ingestion Example (Disaster Domain)

This example demonstrates how to turn **social-style messages** into usable machine features for disaster response, using:

- `NumericalScaler` (normalization)
- `CategoricalEncoder` (one-hot encoding)
- `InteractionFeatures` (simple feature engineering)

The core library stays generic; this script is a domain-specific illustration.

## Scenario

We simulate a small set of posts about a flooding event:

- Messages mention bridges, hospitals, roads, and official guidance.
- Each post has:
  - `source` (e.g., `twitter`, `facebook`)
  - `channel` (e.g., `public`, `community`, `official`)
  - `text`, `likes`, `shares`

From these, we derive a feature vector per post suitable for downstream models (KNN, Bayes, decision trees, etc.).

## How it works

For each post:

1. **Numeric features**:
   - `likes`, `shares`, `text_length`
   - Each scaled to zero-mean, unit-like variance with `NumericalScaler`.
2. **Categorical features**:
   - `channel` and `source`
   - One-hot encoded with `CategoricalEncoder` (with a default `UNKNOWN` category).
3. **Interaction features**:
   - `InteractionFeatures` adds pairwise products between features, capturing simple interactions.

## How to run

From the project root:

```bash
php examples/disaster_response/social_media_ingestion/run.php --seed=123
```

## Outputs

JSON to stdout:

- `seed`: RNG seed used for any stochastic choices (currently only for consistency).
- `posts`: list of objects:
  - `id`, `source`, `channel`, `text`
  - `features`: numeric array representing the engineered feature vector.

These feature vectors are intended to be plugged into other Automata strategies (KNN, Bayes, DecisionTree, etc.) for downstream tasks such as priority scoring or classification.

