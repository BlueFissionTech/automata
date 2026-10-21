# Feedback under noise and changed objectives

Run `php examples/generic/cortex/feedback_restart.php` from the repository root.
This deterministic experiment exercises the actual compound Input/Sense/Engine
agent in four PHP processes, using the existing anchored route advisor and atomic
file store. It adds no provider calls, model fitting, dependency or public policy
restriction.

## Protocol

1. Configure the anchor and admit balanced contradictory reviews, one neutral
   review and one absent review. They must preserve the initial route preference.
2. Execute 32 sessions. Evaluate each actual response against an independent
   fixture objective that requires the media observation. Invert one review in
   seven and apply reviews only after batches of four executions. Persist the
   policy and attributable session journal together.
3. Start another process and verify the learned behavior before supplying any
   new feedback. Then reverse the external objective while keeping the observed
   input distribution unchanged: the expected response now omits media. Execute
   64 more sessions with the same noise and delay protocol. This deliberately
   exposes the old policy's failure before testing recovery from continued review.
4. Start another process, replay both epochs, and verify that replay changes no
   policy evidence or score and that recovery survives the restart.

Training uses synthetic frames with 4, 6 and 8 light pixels. Evaluation uses
3, 5, 7 and 9, plus unchanged text-only and blank/low-signal cases. Evaluation
outcomes are never admitted to the learner. These small disjoint values test
coarse signature sharing; they do not establish statistical generalization.

The journal retains actual executed Experiences, the external objective version,
independently scored correctness, supplied reward and deliberate review-noise flag.
Neutral/absent evidence remains in the advisor checkpoint. The changed objective
does not rewrite the earlier observations. A deployment would need authenticated
review and its own versioned admission policy; a fixture noise flag is not a way
to identify unreliable real reviewers.

## Efficacy and response work

The probe reports correctness and a declared response-work proxy separately.
Quick responses cost one fixture unit; careful responses cost four. These numbers
are not measured latency, provider spend or total graph-execution costs. The
fixture utility is accuracy minus 0.1 times mean response work. It is reported as
one explicit tradeoff rather than a universal training or promotion objective.

The deterministic run passes all 15 gates:

| Policy and current objective | Correct | Mean response units | Fixture utility |
| --- | ---: | ---: | ---: |
| Anchor; include media | 4/8 | 1 | 0.40 |
| After 32 noisy reviews; include media | 8/8 | 2.5 | 0.75 |
| Same learned policy; objective changes to omit media | 4/8 | 2.5 | 0.25 |
| After 64 further noisy reviews; omit media | 8/8 | 1 | 0.90 |
| Another restart and replay; omit media | 8/8 | 1 | 0.90 |

Reversing the objective makes the previous behavior inappropriate for that same
context; it cannot simultaneously satisfy incompatible targets. Text-only and
blank/low-signal contexts remain correct throughout. Evaluation compares each
policy to the objective currently in force, and retains both scores for audit.

The gates require improved quality under noisy delayed review, preservation of
unchanged contexts, both routes remaining useful where appropriate, bounded
adjustments, recovery after the objective reversal, retained recovery, idempotent
replay, denial without response effects and anchor behavior in an unseen modality
family. The fixed sample counts are experiment budgets, not adaptation guarantees.

## What remains to qualify

This complements [coordinated session rollout](session-rollout.md), which trains
a local tree and atomically activates its compatible route/model/workflow bundle.
It does not replace that experiment, or the separate KNN/Bayes/tree and Monte
Carlo restart tests in the [session-learning guide](session-learning.md).

An unobserved objective change cannot be predicted here: recovery starts only
after new feedback. The cumulative advisor can adapt slowly after much longer
histories. This experiment neither introduces a drift detector nor establishes a
safe evidence-decay policy. Deliberate one-in-seven noise is not adversarial
review qualification. The advisor also does not explore alternate routes on its
own; the Monte Carlo game has a separately bounded legal-action exploration
protocol. Hosts must define and qualify exploration and cost limits for their
capabilities before enabling it.

Independent host conformance, larger and varied task distributions, representative
feedback, and measured resource tradeoffs remain necessary for deployment claims.
The existing conversational, operational, game-player and NPC examples exercise
their own bounded scenarios; their results must not be extrapolated to native
host conversations or to every learning policy. No remote model weights change.
