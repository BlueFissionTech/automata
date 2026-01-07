# Generic Data Ingestion + Preprocessing Example

This example shows a simple, framework-agnostic way to turn **tabular records** into normalized, encoded feature vectors using Automata’s encoding and normalization helpers.

## What it demonstrates

- Using `NumericalScaler` to normalize numeric columns.
- Using `CategoricalEncoder` to one-hot encode categorical columns.
- Producing feature vectors suitable for downstream models or analytics.

## Scenario

We simulate sensor readings from multiple stations:

- Fields per record:
  - `station` (identifier)
  - `temp` (numeric)
  - `humidity` (numeric)
  - `type` (categorical: e.g., `urban`, `rural`)

## How it works

1. Extract numeric columns (`temp`, `humidity`) and scale each independently.
2. Extract categorical `type` values and fit a one-hot `CategoricalEncoder`.
3. For each record, build a feature vector:
   - `[scaled_temp, scaled_humidity, one_hot(type)...]`

## How to run

From the project root:

```bash
php examples/generic/data_ingestion/run.php
```

## Outputs

JSON to stdout:

- `records`: list of objects:
  - `station`: station identifier
  - `raw`: original row
  - `features`: normalized + encoded feature vector

This pattern can be adapted to any structured dataset before feeding into Automata strategies, other ML models, or analytics pipelines.

