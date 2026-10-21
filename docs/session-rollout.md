# Retained session-to-training rollout

Run `php examples/generic/cortex/session_restart.php` from the repository root.
The example connects existing Automata utilities into a six-process agent
experiment: compound Input/Sense/Engine processing, observed sessions, delayed
review, bounded route adaptation, isolated local fitting, compatible publication,
restart and rollback. It requires no provider credentials or external services.

## What each process does

1. **Initialize:** run two initial configured examples through the compound agent,
   project their explicit anchor labels and fit a local decision tree. Publish the
   initial model, route policy, workflow-builder recipe and training checkpoint.
2. **Capture:** restore the active bundle in a fresh process, execute six compound
   sessions and persist actual observations in a separate atomic journal. This
   process admits no labels, fits no model and changes no active bundle.
3. **Train:** restore the executions and apply explicit fixture review. Three
   sessions receive an `assist` label and signed quality feedback derived from their
   recorded output. A neutral review, an absent review and contradictory labels
   remain distinct. Only three unambiguous approved labels become new examples.
   Rehearse the two anchor examples, train an isolated candidate and evaluate the
   whole proposed bundle. Denied promotion leaves the anchor active; separately
   approved promotion publishes the compatible set and receipt together.
4. **Restore:** load the fitted artifact and retained training/advisor checkpoints.
   Replay training, feedback and promotion identities with no new fitting or
   authorization. Execute new inputs and verify preserved behavior, context
   isolation and independent host denial.
5. **Rollback:** verify and restore the previous complete bundle.
6. **Verify rollback:** start again and confirm the prior behavior without fitting.

The journal captures observations before review. The accepted learning artifact
retains reviewed sessions, the journal digest, training lineage and request
receipts. The manifest binds the active and anchor model artifacts, route policy,
registered workflow builder and learning checkpoint by exact versions and hashes.
The builder dynamically creates per-pass graphs from current signatures; no fixed
graph is imposed on the general library. Captured experiences also retain the
actual processing-pattern definitions, feature schema, selected workflow/node,
attempt and model identity where that selected worker actually used a model.

## Evidence and credit assignment

The first graph observes text and synthetic binary video frames. Subsequent
signatures append text/media branches, wait for their join, then route a response.
`CompoundCortex` optionally binds a host-owned local intent predictor to the
careful response worker. The quick worker does not use that model and therefore
records no model attribution. Executable callbacks are never loaded from policy
data or created by a learned preference.

Signed workflow-quality feedback updates the selected route only. It does not
automatically penalize every upstream node or create a classifier target.
`SessionProjection` is an example-owned admission adapter: an independently
approved, unambiguous human target supplies the model label, using features from
captured Sense observations. Successful execution and positive/negative/zero
reward alone supply no label. Conflicting approved labels abstain. Reviewer
authentication and authorization belong to the host; a field named `approved`
is not authentication for untrusted input.

Each review records source, scope, confidence and reward-policy version. Initial
labels remain identifiable training anchors. Replaying reviewed experiences must
leave the restored advisor checkpoint unchanged. A new modality combination keeps
its configured route rather than silently inheriting another signature's scores.

## Frozen evaluation and controls

The evaluation has three challenges with sensor feature counts 5, 7 and 9, disjoint
from the new training counts 4, 6 and 8, plus two checks of the initial capability.
The fixtures use different session identities and text. Evaluation records never
enter training or feedback admission.

| Configuration | Correct evaluation cases |
| --- | ---: |
| Initial bundle | 2/5 |
| Improved model with initial route policy | 2/5 |
| Improved policy with initial model | 2/5 |
| Compatible improved model and policy | 5/5 |
| Fresh process restoring the accepted bundle | 5/5 |
| Rollback, including another restart | 2/5 |

These controls expose two separate defects: the initial route omits media evidence,
and the initial local model lacks the new label family. Changing both is necessary
to pass this experiment. The 23 executable gates additionally check branch/join
waiting, delayed review, version attribution, replay, denial and retained anchors.
Focused projection tests reject invented labels and ambiguous review.

## Qualification boundary

This is a synthetic application assembly using the general library contracts.
Binary pixels, light counts, label vocabulary, reviewer rules, evidence thresholds,
builder identity and evaluation targets belong to this example. They do not limit
Automata's modalities, graph topology, strategy catalogue or training algorithms.
The small result is neither statistical generalization nor real vision.

The integrated model is a fitted decision tree; separate [model restart
examples](session-learning.md) qualify KNN, Bayes and Monte Carlo with their own
adapters and evaluators. [Bundle recovery](learning-bundles.md) separately tests
process interruption at publication. This example does not claim exactly-once
fitting across a trainer crash, transactional distributed training, power-loss
durability, native host conversation or real media qualification. Training,
activation and external effects continue to require separate host authority.
