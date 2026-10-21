# Cortex conformance example

This example is built incrementally from reusable Automata contracts. The first
slice records structured experiences and observed labels, recomposes a training
batch, and evaluates a real Naive Bayes strategy on held-out concierge requests.
The fixtures are synthetic, provider-free, and bounded; this is an experiment,
not evidence of general conversational intelligence or production readiness.

Use a checkout containing all twenty-one deterministic
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
| `host_receipts.php` | 7 | Host journal stand-in, scoped recovery, stale writer and uncertain-result gates |
| `agent.php` | 17 | Agent workers, governed fixture tools, scope checks and TaskTrace |
| `promote.php` | 8 | Approved activation changes inference; rollback restores it |
| `learn.php` | 15 | Recorded experience triggers approved training, activation and changed Agent plans |
| `workflow.php` | 12 | Graph execution, overlapping workers, fan-in and ordinary Intelligence selection |
| `sensory.php` | 22 | Input normalization, default/custom Sense observations, experience projection and labelled classification |
| `script.php` | 16 | Real parser rendering, governed generation slots, early exit, routing and trace evidence |
| `behavior.php` | 7 | DevElation state/behavior gates, host denial and unconfirmed request evidence |
| `scenarios.php` | 38 | Conversation, operations, paired game outcomes, NPC interruption and two correction batches |
| `confidence.php` | 7 | Unknown confidence, nested agents, strict scoring and measured fallback |
| `replay.php` | 10 | Recorded provider mistakes change routing and improve unseen game decisions |
| `prerequisites.php` | 11 | Non-completed prerequisites stop dependents; only Boolean verification certifies |
| `status.php` | 114 | Independent peers preserve incomplete outcomes; blocked controllers prevent dispatch |
| `reinforcement.php` | 5 | Signed reward is separate from execution; neutral, absent and sparse feedback preserve anchors |
| `reinforcement_restart.php` | 7 | Retained evidence changes actual routing across clean process restarts without duplicate learning |
| `compound.php` | 20 | Input/Sense/Engine observations change signatures, recompose graphs, wait for joins and adapt a workflow node through bounded session feedback |
| `model_restart.php` | 16 | KNN/Bayes artifacts and reviewed sessions survive restarts; continued fitting rehearses prior examples and duplicate requests do not retrain |

Each command emits JSON evidence and exits nonzero if a required gate fails. All
370 gates run in CI alongside PHPUnit. `learn.php` assembles the experience-to-response
loop in one process; `workflow.php` adds cooperative Fiber overlap. Durable worker
recovery and asynchronous response producers remain open. The
[delivery guide](../../../docs/cortex-delivery.md) maps integration and open work.

The [session-learning guide](../../../docs/session-learning.md) describes bounded
signed reinforcement and checkpoints. The restart fixture proves clean-process
route retention; it does not qualify crash recovery, compound sensing, broad
generalization or coordinated local-model retraining.

The [agent proof rubric](../../../docs/cortex-agent-proof.md) records frozen
splits, measured outcomes, provider failures and limits. Optional
`live_scenarios.php` calls are explicitly opt-in and are not part of default CI.

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

Run `php examples/generic/cortex/host_receipts.php` for a separate, bounded host
recovery acceptance fixture. It uses an in-memory journal and receiver result map
to demonstrate compare-and-swap checkpoints, scoped lookup, lost-ack reconciliation,
and unknown-result rejection. It does not provide durable storage, authenticated
lookup, an atomic external receiver, or production effect execution. See the
[host acceptance contract](../../../docs/cortex-delivery.md#host-receipt-and-recovery-acceptance-fixture).

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

Run `php examples/generic/cortex/sensory.php` for a separate ingress experiment.
It passes bounded text through real Input processors and Sense sweeps, preserves
raw/normalized evidence, creates Experiences and trains an isolated classifier
only from externally labelled observations. Custom preparation preserves domain
words; extra gates exercise default preparation and retained results. This does not exercise InputArray
or qualify general multimodal ingestion. See [sensory ingestion](../../../docs/sensory-ingestion.md)
for placement, measured behavior, extension points and remaining work.

Run `php examples/generic/cortex/behavior.php` to see DevElation `StateMachine`
and `State`/`Action` behaviors through Automata's `AgentState`. A route proposal
is allowed while reviewing, dispatch is denied, and a state transition permits
the local dispatch behavior. A separate host decision can still deny it. Even
after host approval, the fixture retains a requested, unconfirmed status because
no receiver receipt exists. This example performs no external action.
