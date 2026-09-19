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

Run `php examples/generic/cortex/adapt.php` for the next step: explicitly admit
attributed benchmark outcomes into advisory strategy feedback, then observe a
new request select the better candidate. Its ten gates also check replay handling,
deterministic preference, eligibility, authorization, exact versions and invocation
budgets. The host pre-registers both strategies and explicitly allows adaptive
selection across their modes. No model is automatically promoted or deployed.
Feedback receipts retain the source and strategy/version/context lineage. This is
process-local evidence; durable recovery, provider costs and energy are not proved.

Run `php examples/generic/cortex/respond.php` for progressive response composition.
It uses the existing classifier, waits for blocking requirements, releases an
early acknowledgement and a simulated action separately, and withholds confirmation
until a successful receiver receipt. A lost-ack restart reuses the same delivery
identity; a retained fixture receiver performs only one effect. Additional probes
exercise cancellation and deadline fallback. See
[`docs/response-composition.md`](../../../docs/response-composition.md) for the
checkpoint, terminal receipt and host persistence contract.

Subsequent slices add controlled promotion, durable governed adaptive routes,
Agent/worker/TaskTrace response integration, composite/scripted strategies and shared goal criteria.
Each slice must report its actual measurements and unresolved release gates.
