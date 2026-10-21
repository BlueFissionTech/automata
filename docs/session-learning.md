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
