# Cortex conformance example

This example is built incrementally from reusable Automata contracts. The first
slice records structured experiences and observed labels, recomposes a training
batch, and evaluates a real Naive Bayes strategy on held-out concierge requests.
The fixtures are synthetic, provider-free, and bounded; this is an experiment,
not evidence of general conversational intelligence or production readiness.

Run `php examples/generic/cortex/run.php` from the repository root. The command
prints machine-readable evidence and exits nonzero when its conformance checks
fail. No network, credentials, or external side effects are required.

Subsequent slices add candidate comparison, governed adaptive routes, progressive
response dependencies, composite/scripted strategies, and shared goal criteria.
Each slice must report its actual measurements and unresolved release gates.
