# Retained session learning

This work extends the general Input → Sense → Intelligence/Engine assembly.
Input signatures can describe multiple modalities and can change after every
processing pass. Processing patterns may branch, append, join or be replaced
as new observations become available. Example labels, candidate counts and
graph shapes are fixtures, not restrictions on those contracts.

## Evidence and reinforcement

Execution success is not a reward. A completed action may receive negative
feedback; an interrupted action may receive positive feedback for correctly
stopping. A zero-valued `FeedbackSignal` is neutral. A missing signal means
no evaluation was supplied. Neither implies failure.

The first component, `AnchoredRouteAdvisor`, is an opt-in implementation of
the existing route-advisor interface. It consumes explicitly admitted
Experience/Outcome evidence and signed feedback. It does not change the
legacy `Intelligence` outcome-scoring behavior.

An immutable configured prior anchors each exact strategy/version. Signed
rewards are normalized using an explicitly configured reward scale. After a
minimum number of directional observations, a prior-weighted mean adjusts the
anchor by at most the configured maximum adjustment. Neutral and absent
evaluations are retained but do not change directional evidence or preference.
These statistics are not calibrated confidence.

Each context is independent: there is no implicit update to a global bucket.
The host defines the context/signature projection and must version it when its
meaning changes. Exact outcome identity prevents duplicate learning; conflicting
feedback or changed source evidence is rejected. Transport observations alone
cannot produce reinforcement. Advice never adds candidate capabilities or
changes the route executor's authority checks.

This is a bounded reward-estimation policy, not a claim of general optimal
reinforcement learning. Alternative policies can implement the same advisor
interface. Exploration, signature generalization and coordinated training need
their own explicit contracts and qualification.

Run `php examples/generic/cortex/reinforcement.php` for the reward semantics,
and `php examples/generic/cortex/reinforcement_restart.php` for retained route
selection across three independent PHP processes. The restart fixture collects
explicit review of completed actions, writes a checkpoint through DevElation
Disk, and reconstructs the advisor twice. Each restored process must select the
learned action, preserve unseen-context behavior and ignore replayed evaluations.

## Checkpoints and activation

`checkpoint()` exports data-only evidence and the complete immutable policy.
`restore($checkpoint)` returns a separate advisor rebuilt from that evidence;
the receiver is unchanged even if restoration fails. Hosts explicitly activate
the candidate with `StrategyRouter::advisor($candidate)`. Retain the prior advisor
or its checkpoint for rollback. Imported scores are never accepted as learning.

The policy includes its id/version, exact strategy anchors, default anchor,
reward scale, prior weight, adjustment bound and sample/retention limits. Every
field must match when restoring. Changed reward meanings, signature projections
or anchors require a separately evaluated migration. Context keys are exact,
including whitespace; the host owns their canonicalization and versioning.

Persist checkpoints using a format that preserves scalar types; for JSON use
`JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION`. The fingerprint detects
accidental alterations, but it is not authentication. Hosts must admit trusted
feedback and protect checkpoint access. The example's single-writer Disk file
proves clean restart retention; atomic writes, crash recovery and concurrent
writer ownership still require qualified storage contracts.

An identical outcome replay is ignored. A new assessment uses a new outcome id
on the same unchanged source experience. Conflicting evidence under an existing
identity is rejected. Capacity exhaustion rejects new evidence without evicting
identities; hosts must plan retention and policy migration explicitly. Neutral
and absent evaluations occupy audit capacity but do not dilute earlier rewards.
The sample threshold counts directional outcomes, not statistically independent
sessions. Correlated evaluations and distribution shift need separate admission
and held-out evaluation controls before automatic promotion.

## Required complete flow

### Compound signatures and adaptive workflow nodes

`Sensory\InputSignature` captures a host-defined, versioned projection of finite
features. Construct a new signature after each processing pass, or call
`withFeatures()` to replace the complete projection immutably. Its `key()` can be
used as a route request's `context_key`. Map key order is immaterial; list order,
scalar types, projection identity and projection version remain significant.
`toArray()` and `fromArray()` preserve the data contract, not executable projection
code or authorization. There is no fixed feature vocabulary or modality catalog.

Choose features that define reusable processing classes: modality/MIME groups,
size buckets, interpreted signals and the semantic processing stage. Avoid session
ids, timestamps or full payload fingerprints unless they intentionally define
separate learning contexts. Coarse features do not by themselves establish that
two inputs should generalize; version and evaluate the projection as host policy.

A workflow node may now declare either its existing `strategy_id` and
`strategy_version`, or a nonempty `candidates` list containing exact `id`/`version`
pairs. Mixing both forms, duplicate pairs and malformed records are rejected.
Optional `selection_policy` accepts `declared` (default) or `adaptive`.
`deterministic_preferred` remains `true` by default; choosing a learned or
generative candidate also requires admitting its mode through `allowed_modes`.
The router's capability, current authorization, eligibility and budget checks
continue to apply. Advice cannot add a strategy to the declared candidate set.
Legacy single-strategy exports retain their existing canonical representation.

Run `php examples/generic/cortex/compound.php` for real Input/Sense/Engine
processing with text and synthetic binary sensor frames. An initial graph joins
sensor observations. New facts change the projected signature and cause the
next graph to append relevant text/media branches and join their results. A
third graph selects an explicitly admitted response strategy using the retained
feedback policy. Frozen plan versions distinguish different topologies; running
graphs are not silently edited. Completed results and prior signatures remain in
the trace. Cooperative Fiber waves demonstrate that the join waits until both
appended workers finish; they do not establish CPU parallelism or worker durability.

The example checks changed behavior on a new utterance/frame, blank sensors,
unseen modality combinations, sparse contradictory feedback, denial and restored
preferences. Pixel formats, thresholds, labels and graph recipes are fixture
policy. This does not establish general video recognition, calibrated confidence,
statistical generalization, model-node retraining or durable worker recovery.

### Retained local-model training

`LearningCoordinator::evidence()` exposes the immutable lineage of the configured
anchor and successfully trained batches. For a replacement model that should
rehearse earlier examples, pass `evidence()->merge($newLabels)` to `train()`.
`TrainingBatch::merge()` retains order and exact lineage, ignores identical
replays and rejects a changed sample/label under an existing outcome identity.
Projection id and version must agree. `TrainingBatch::fromArray()` restores the
finite data contract. Admission and deliberate correction labels remain host
responsibilities; workflow rewards are not automatically labels for every node.

`LearningCoordinator::checkpoint()` exports quiescent request/receipt history,
training provenance, policy and retention configuration, and event errors.
`restore($checkpoint, $load)` creates an isolated coordinator under the exact same
configuration. It validates the complete history before invoking a trusted host
loader with `(array $identity, TrainingBatch $training): IStrategy`. The loader
must return independent model instances and verify artifact integrity, scope and
backend compatibility. Restore does not fit models, emit training events, repeat
authorization or activate a candidate. Replayed requests return their retained
results. Deferred/denied requests do not reserve a version; uncertain training
remains blocked rather than being silently retried after a restart.

The checkpoint fingerprint is corruption detection, not authentication. Protect
both the checkpoint and the separately stored artifacts. The existing PHP-ML
save/load implementations use serialized objects; only load trusted artifacts.
The checkpoint deliberately carries no executable class or arbitrary file path.
Persist it with type-preserving JSON (`JSON_PRESERVE_ZERO_FRACTION`) or an
equivalent format. This API describes recovery state; it does not itself provide
an atomic storage transaction, multi-writer ownership or bundle activation.

Run `php examples/generic/cortex/model_restart.php` for three actual PHP processes:
fit KNN and Naive Bayes from reviewed sessions, restore artifacts and add another
input family while rehearsing earlier examples, then restore without fitting.
The fixture retains actual predictions, exact model identities, target labels
and signed review separately from execution completion. Disjoint holdout sessions
evaluate new and previous capabilities; identical training requests do not run
again. `NaiveBayesTextClassification::train(..., testSize: 0.0)` now fits the whole
admitted batch when an independent evaluator owns the holdout, matching the KNN
path. An empty internal holdout retains the existing `accuracy()` value of zero;
use the external evaluation report rather than treating that value as evidence.

These synthetic families demonstrate retained local fitting and continued
training, not statistical generalization or complete Cortex activation. Fitted
trees, Monte Carlo statistics, coordinated policy/model publication, interrupted
commit recovery and broader independent host scenarios remain to be qualified.

### Remaining qualification

The completed session-learning assembly must:

1. Exercise real Input/Sense and Intelligence/Engine processing with compound
   signatures, per-pass changes and composed workflow branches/joins.
2. Retain admitted evidence, feedback identity and policy revisions across
   separate processes; validate crash/replay, wrong scope and corrupt records.
3. Train isolated local candidates using appropriate projections: supervised
   examples for KNN/Bayes/fitted trees, distinct statistics or priors for Monte
   Carlo methods. A workflow reward is not automatically a label for each node.
4. Evaluate held-out sessions and input families, previous capabilities, neutral
   and contradictory feedback, and resource/efficacy tradeoffs.
5. Activate compatible workflow, route-policy and model versions together, with
   a retained anchor and rollback. Distributed writers require explicit storage
   and ownership semantics beyond the initial single-writer qualification.

The existing game restart probe demonstrates reconstruction only. It does not
complete the requirements above. No provider weights are changed.
