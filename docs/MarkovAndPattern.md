# Markov and Pattern Modules Overview

This document summarizes the Markov and pattern-related classes that have been
added or updated, and how they are tested and used in examples.

## Language Markov Predictors

### `MarkovPredictor` (Unigram)

Namespace: `BlueFission\Automata\Language`.

- Uses `Automata\Collections\OrganizedCollection` to track word transitions.
- `addSentence($sentence)`:
  - Tokenizes the sentence and records the first word as a possible beginning.
  - For each `(previousWord, word)` pair, maintains a per-word
    `OrganizedCollection` of transitions with weights.
- `predictNextWord($currentWord)`:
  - Aggregates weights from the `OrganizedCollection` entries and samples a
    next word using `mt_rand` weighted by counts.
- Serialization:
  - `serializeModel()` and `deserializeModel()` store and restore `states` and
    `beginnings` so predictions stay consistent across runs.

**Tests:** `tests/Automata/Language/MarkovPredictorTest.php`

- Confirms:
  - For a fixed seed, `predictNextWord('hub')` is deterministic.
  - Serialization + deserialization preserve predictions under the same seed.

### `TrigramMarkovPredictor`

Namespace: `BlueFission\Automata\Language`.

- Uses `OrganizedCollection` to map a two-word context (`"w1 w2"`) to a simple
  `nextWord => count` array.
- `addSentence($sentence)` builds trigram counts; short sentences are skipped.
- `predictNextWord($sentence)`:
  - Extracts the last two words, looks up trigram counts, and samples the next
    word using `mt_rand` weighted by counts.

**Tests:** `tests/Automata/Language/TrigramMarkovPredictorTest.php`

- Ensures that, for a given seed, `predictNextWord('hub road')` is
  deterministic.

### Example

- `examples/markov_logistics_language.php`
  - Trains both predictors on short logistics-related phrases (`hub road open`,
    `hospital supply delayed`, etc.).
  - Demonstrates deterministic next-word predictions for several contexts
    under fixed seeds.

## Strategy-Level Markov & N-Gram

### `MarkovTextPrediction` (Strategy)

Namespace: `BlueFission\Automata\Strategy`.

- Adapts `Phpml\Classification\MarkovChain` to the `Strategy` interface.
- `train(array $samples, array $labels, float $testSize)`:
  - Treats each sample as a sentence, tokenizes, and builds word pairs for
    transitions.
  - Trains the underlying `MarkovChain` and creates a test set of pairs.
- `predict($input)` predicts the next word for a given previous word.
- `accuracy()` compares predictions on the test set using `Phpml\Metric\Accuracy`.
- Tests are present but skipped automatically if `php-ai/php-ml` is not
  available in the environment.

### `NGramTextPrediction` (Strategy)

Namespace: `BlueFission\Automata\Strategy`.

- Implements a simple frequency-based n-gram predictor under the `Strategy`
  interface.
- `train(array $samples, array $labels, float $testSize)`:
  - Uses `Phpml\Tokenization\WhitespaceTokenizer` and `NGramTokenizer` to build
    `(context → nextWord)` counts from sentences.
  - Stores a test subset of n-grams for accuracy measurement.
- `predict($input)`:
  - Accepts an array of previous words, builds a context string, and returns
    the most frequent next word for that context (or `''` if none).
- `accuracy()` scores predictions on the test subset.
- Tests are present but skipped if the tokenizer class from php-ml is missing.

## Pattern Strategy

### `Pattern`

Namespace: `BlueFission\Automata\Strategy`.

- Extends `Basic` to learn and predict sequences of discrete values.
- `train(array $samples, array $labels, float $testSize)`:
  - Interprets each sample as a sequence (e.g., `['a','b','c','d']`).
  - Ignores labels; stores flattened sequences in `_rules`.
- `predict($val)`:
  - Appends `$val` to an internal buffer and attempts to match the buffer as a
    prefix of any stored rule.
  - On a match, returns the next element in that rule as the prediction.
  - Tracks `_success` when predictions exactly match actual values, so
    `accuracy()` remains meaningful.
  - Falls back to a random value from a random rule when no pattern is found.

**Tests:** `tests/Automata/Strategy/PatternTest.php`

- Ensures:
  - Training on a single sequence yields non-empty predictions.
  - After training on `['a','b','c','d']`, `predict('a')` returns `'b'`.
  - Accuracy is computed after a small sequence of predictions.
  - Save/load preserves behavior (prediction after reload still returns `'b'`
    for `'a'`).

This pattern strategy is used for simple sequence prediction and anomaly
detection foundations (e.g., expecting a particular next symbol in a known
pattern).

## KNN Regression & Related Utilities

Beyond Markov and pattern matching, Automata includes several KNN-based helpers
that are particularly relevant for numeric prediction and anomaly detection.

### `KNearestRegression`

Namespace: `BlueFission\Automata\Strategy`.

- Implements a simple KNN regressor under the `Strategy` interface.
- `train(array $samples, array $labels, float $testSize)`:
  - Stores full training samples and continuous targets.
  - Uses Euclidean distance as the similarity metric.
- `predict($input)`:
  - Converts the input to a vector and returns a distance-weighted average of
    the K nearest neighbor targets.
- `neighbors(array $features, int $k)`:
  - Returns the top-k neighbors as `['index' => i, 'distance' => d]` pairs.
- `accuracy()`:
  - Computes RMSE on the test set derived from the training data.

**Tests:** `tests/Automata/Strategy/KNearestRegressionTest.php`

- Verifies that, on a simple synthetic dataset where the target is approximately
  the sum of features, predictions for new points are close to that sum.
- Validates that `neighbors()` returns the closest points by index.

### `KNearestExplorer`

Namespace: `BlueFission\Automata\Analysis`.

- Generic neighbor search utility:
  - Stores feature vectors and optional IDs.
  - `neighbors($features, $k)` returns:
    - `id` (user-supplied or index),
    - `index` in the stored samples,
    - `distance` to the query point.
- Useful for neighbor explanations independent of any particular Strategy.

**Tests:** `tests/Automata/Analysis/KNearestExplorerTest.php`

- Confirms that neighbor IDs and ordering are as expected on a small numeric
  dataset.

### `KNearestAnomaly`

Namespace: `BlueFission\Automata\Analysis`.

- Wraps a `KNearestExplorer` to provide:
  - `score($features, $k)` – average distance to the K nearest neighbors.
  - `isAnomalous($features, $k, $threshold)` – true if the score exceeds the
    supplied threshold.
- Suitable for spotting outliers in feature space, including:
  - suspicious requests,
  - unusual sensor readings,
  - resource abuse patterns.

**Tests:** `tests/Automata/Analysis/KNearestAnomalyTest.php`

- Ensures that a far-away point in feature space receives a higher anomaly
  score than a near point and is flagged as anomalous above a chosen threshold.

### `BetaReliability`

Namespace: `BlueFission\Automata\Analysis`.

- Maintains Beta(a,b) posteriors for Bernoulli processes (success/failure):
  - `update($key, $success, $weight)` adjusts alpha/beta for the given key.
  - `mean($key)` returns the posterior mean reliability `a / (a + b)`.
  - `parameters($key)` exposes raw `alpha` and `beta` values.
- Typical applications:
  - edge passability,
  - asset/team reliability,
  - resupply success probabilities.

**Tests:** `tests/Automata/Analysis/BetaReliabilityTest.php`

- Validates that repeated updates yield the expected alpha/beta parameters and
  posterior mean for a key.

### KNN ETA Example

- `examples/knn_eta_prediction.php`
  - Demonstrates KNN regression and reliability together:
    - Uses `KNearestRegression` to predict ETA for a new logistics request
      based on synthetic historical missions.
    - Uses `KNearestExplorer` to list the nearest historical missions and their
      ETAs as an explanation.
    - Uses `BetaReliability` to compute a simple reliability score for an
      asset (e.g., `asset:Truck-A`).

