# Cortex conformance example

This example is built incrementally from reusable Automata contracts. The first
slice records structured experiences and observed labels, recomposes a training
batch, and evaluates a real Naive Bayes strategy on held-out concierge requests.
The fixtures are synthetic, provider-free, and bounded; this is an experiment,
not evidence of general conversational intelligence or production readiness.

These contracts are staged development work. Use a checkout containing all eight
scripts and install its Composer dependencies before running from the repository
root. PHP 8.2+ is required by the library; CI uses PHP 8.3 for the locked test
toolchain. No provider credentials, network calls or external services are needed
to execute the demos after installation.

| Command (`php examples/generic/cortex/...`) | Gates | Observable result |
| --- | ---: | --- |
| `run.php` | 11 | Experience projection, exact frozen lineage/predictions and negative controls |
| `evaluate.php` | 5 | Better candidate recommended; regression rejected and unknown cost preserved |
| `adapt.php` | 10 | Admitted feedback changes routing while policy gates remain enforced |
| `respond.php` | 12 | Progressive release, terminal receipts, replay, cancellation and fallback |
| `agent.php` | 17 | Agent workers, governed fixture tools, scope checks and TaskTrace |
| `promote.php` | 8 | Approved activation changes inference; rollback restores it |
| `learn.php` | 15 | Recorded experience triggers approved training, activation and changed Agent plans |
| `workflow.php` | 12 | Graph execution, overlapping workers, fan-in and ordinary Intelligence selection |

Each command emits JSON evidence and exits nonzero if a required gate fails. All
90 gates run in CI alongside PHPUnit. `learn.php` assembles the experience-to-response
loop in one process; `workflow.php` adds cooperative Fiber overlap. Durable worker
recovery and asynchronous response producers remain open. The
[delivery guide](../../../docs/cortex-delivery.md) maps integration and open work.

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

Run `php examples/generic/cortex/agent.php` to exercise the response contract through
real Agent orchestration, governed fixture tools and TaskTrace. It emits early text,
classifies a request, produces a tool plan and waits for a terminal receipt before
confirmation. Additional probes cover host scope/version checks, permission denial
and revocation after preparation, cancellation and uncertain effects without retry.
The host receiver ledger is retained in memory across a simulated caller restart;
the example does not prove durable recovery, concurrency or production authorization.

Run `php examples/generic/cortex/promote.php` for controlled reference activation
and rollback. Fresh evaluation and explicit host approval precede activation;
regressions, denial, stale revisions and conflicting retries cannot replace the
active model. The example observes actual prediction changes and reversal. This
does not deploy models or mutate unrelated strategy registries. See
[`docs/model-lifecycle.md`](../../../docs/model-lifecycle.md) for ownership and retry limits.

The shared corpus is frozen in `fixture-v1.json`; `baseline-v1.json` pins its digest,
projection lineage and all six expected predictions. The foundation demo also checks
constant and single-wrong negative controls. Baseline changes require explicit review
and a new version, rather than accepting new output merely because it was produced.

Run `php examples/generic/cortex/learn.php` for the integrated training loop. It
records observations, defers insufficient evidence, separately approves training
and activation, and changes a future Agent plan while preserving tool approval
and delivery receipts. See [continual learning](../../../docs/continual-learning.md)
for pressure signals, callback contracts, retention and uncertain-failure handling.

Run `php examples/generic/cortex/workflow.php` for the graph execution proof: a real
classifier and an independent context worker overlap through Fibers before a join
composes their results. The command also verifies ordinary Intelligence selection,
denial, cancellation, trace correlation and plan export/import. See
[strategy workflows](../../../docs/strategy-workflows.md) for conditional edges,
fallback, bounded retries, race/threshold completion and host responsibilities.

Subsequent slices add learned and durable adaptive routes,
concurrent response persistence, scripted strategies and shared goal criteria.
Each slice must report its actual measurements and unresolved release gates.
