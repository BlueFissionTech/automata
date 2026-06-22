# Feedback Review Records

Automata feedback primitives describe different parts of a feedback loop:

- `Projection` is the expected or predicted value that may later be checked.
- `Observation` is the value or evidence observed at review time.
- `Assessment` is the strategy result that compares a projection with an
  observation.
- `FeedbackSignal` is a small positive or negative score.
- `FeedbackRegistry` accumulates signals by subject.
- `ReviewRecord` is the neutral evidence envelope for human review, correction,
  policy decisions, and training-signal evidence.

`ReviewRecord` does not execute policy gates. Runtime adapters can keep policy
gates in their host application, or they can use Automata governance classes
such as `TaskCallMonitor` and `HumanReviewGate`. In both cases, the review
record stores the result in a reusable shape.

Stable fields:

- `status`
- `original_value`
- `corrected_value`
- `actor`
- `reason`
- `confidence`
- `timestamp`
- `trace`
- `evidence`
- `policy_strategy`
- `tags`
- `context`

Use `policy_strategy` to name the policy or review strategy that produced the
record. Use `trace` for task, tool, or model trace identifiers, and `evidence`
for source snippets, scores, signal values, or reviewer notes.

Correction records should preserve both the original and corrected values.
Training signal records should keep the signal value in evidence rather than
only storing a confidence score.
