# Cortex conformance example

This example is built incrementally from reusable Automata contracts. The first
slice records structured experiences and observed labels, recomposes a training
batch, and evaluates a real Naive Bayes strategy on held-out concierge requests.
The fixtures are synthetic, provider-free, and bounded; this is an experiment,
not evidence of general conversational intelligence or production readiness.

Run `php examples/generic/cortex/run.php` from the repository root. The command
prints machine-readable evidence and exits nonzero when its conformance checks
fail. No network, credentials, or external side effects are required.

Run `php examples/generic/cortex/evaluate.php` to compare exact model versions on
the shared held-out fixtures. The trained Bayes candidate must improve over the
constant baseline, and a worse replacement must be rejected. The JSON report
contains each prediction's experience/outcome lineage, strict label match,
measured latency, policy thresholds and rejection reasons. Unknown cost and energy
remain `null`. Neither command promotes a model or authorizes operational actions.

`ClassificationEvaluator` rejects shared instances, identical identities,
projection mismatches, repeated holdout experiences/outcomes/exact samples, and
overlap with either declared training corpus before prediction. Limits bound the
number of examples and gate measured mean prediction latency; they do not interrupt
synchronous execution. Models must have trusted, side-effect-free prediction
implementations and independent mutable state. Callers remain responsible for
truthful training provenance, evidence admission, semantic duplication, statistical
confidence, provider spend and any later promotion. Small synthetic fixture wins
alone do not establish production quality.

Subsequent slices add attributed feedback, controlled promotion, governed adaptive routes, progressive
response dependencies, composite/scripted strategies, and shared goal criteria.
Each slice must report its actual measurements and unresolved release gates.
