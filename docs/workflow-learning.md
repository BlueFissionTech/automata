# Retained selection of processing workflows

Run `php examples/generic/cortex/workflow_restart.php`. Three independent PHP
processes combine Input/Sense/Engine observations, versioned input signatures,
adaptive workflow nodes, nested `CompositeStrategy` execution and retained
feedback. This is a separate proof from selecting a final response worker.

The configured anchor selects a one-node text workflow. For compound text/video
inputs, that workflow completes successfully but omits the sensor count required
by independent fixture review. Three training sessions provide signed feedback
against the actual returned observation. The advisor can then select a reviewed
three-node workflow: text and media workers run in cooperative waves and a join
waits for both. Its output supplies a new semantic signature.

The host registers both complete workflows and their executable worker bindings
before learning. The learner selects only among the admitted patterns. It does
not synthesize arbitrary code or mutate an executing graph. Other applications
may supply different graphs, features, candidates, evaluators and policies using
the same library interfaces; these two candidates are fixture configuration.

## Protocol and evidence

Training uses sensor counts 4, 6 and 8. The two frozen evaluation cases contain
different text and sensor counts 3 and 5, and never enter feedback admission.
Their score checks the exact returned words and independently counted pixels,
not the selected route name or execution status.

| State | Correct compound cases | Executed processing workers |
| --- | ---: | --- |
| Fresh configured anchor | 0/2 | text |
| After reviewed sessions | 2/2 | text, media, join |
| First restart and replay | 2/2 | text, media, join |
| Second restart and replay | 2/2 | text, media, join |

The 12 gates require three distinct processes, cold/learned controls, the actual
changed worker ledger, join waiting, changed semantic signature, unchanged
text-only capability, an unknown modality retaining its anchor, denial with zero
dispatches, ignored duplicate reviews, identical restored policy and exact
retention of the original frozen graph proposals. Graph definitions, policy and
the review journal are persisted together through `FileBundleStore`; executable
callbacks are re-bound by the host in every process.

Credit belongs to the selected processing-pattern route. A negative workflow
review does not train or penalize all of its leaf workers. Independent node
labels and fitting are demonstrated by [session rollout](session-rollout.md),
and complete compatible artifact promotion by [learning bundles](learning-bundles.md).
The present probe makes no new model-fitting or activation claim.

## Limits

Binary pixel encoding, the count objective, graph vocabulary and sample counts
are synthetic fixtures. They establish neither real media recognition nor
statistical generalization. Cooperative Fibers expose waiting, not CPU parallelism
or worker durability. Each router retains its own dispatch budget; the example's
outer invocation estimate is not a measured aggregate cost for nested work.
Host authentication, feedback admission and permission remain separate from
learning. This example qualifies clean restart retention; publication failure
and rollback are covered separately by the bundle recovery experiment.
