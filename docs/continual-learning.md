# Evidence-triggered candidate training

`LearningCoordinator` closes the gap between experience projection and candidate
evaluation. It decides whether a bounded training batch warrants work, asks the
host for explicit approval, and trains an isolated candidate. It never activates
that candidate, changes routing registration, or authorizes operational effects.
These contracts are staged development work, not a published release claim.

## Assemble the loop

1. Admit observations into `Experience` and `Outcome` records under host policy.
2. Use `ExperienceRecomposer` and an `ITrainingAdapter` to produce a `TrainingBatch`.
3. Supply a `TrainingTrigger` and let the coordinator assess the batch against its
   retained learned lineage.
4. Approve training with an explicit `GovernanceDecision`, construct independent
   model state, and train through a strategy-specific callback.
5. Pass a trained result's candidate to `ModelLifecycle` with separate labelled
   holdout evidence and separate activation approval.
6. Use the lifecycle's active reference explicitly in future inference. Unrelated
   routers and registries are not updated automatically.

```php
use BlueFission\Automata\Learning\LearningCoordinator;
use BlueFission\Automata\Learning\TrainingBatch;
use BlueFission\Automata\Learning\TrainingPolicy;
use BlueFission\Automata\Learning\TrainingTrigger;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;

$learning = new LearningCoordinator($incumbent,
    new TrainingPolicy(minimumExamples: 6, minimumNewExamples: 12));
$result = $learning->train(
    requestId: 'intent-training-42',
    candidateVersion: 'bayes-42',
    batch: $projected,
    trigger: new TrainingTrigger(),
    factory: static fn () => new NaiveBayesTextClassification(),
    trainer: static function (NaiveBayesTextClassification $model, TrainingBatch $batch): void {
        $model->getPipeline()->train($batch->samples(), $batch->labels());
    },
    authorize: static fn (array $request) => $hostPolicy->reviewTraining($request)
);
if ($result->status() === 'trained') {
    $receipt = $lifecycle->promote('activate-42', $lifecycle->revision(),
        $result->candidate(), $holdout, $reviewActivation);
}
```

The example trainer explicitly uses the full projected corpus. The strategy's
ordinary `train()` method performs its own split; trainer adapters must choose the
appropriate representation and training behavior for their strategy. A callback
must return void only after verified completion and throw on failure. Non-void
returns, including false, are treated as uncertain. A `trained` result means that
this callback completed; measured quality still comes from independent evaluation.
No change to `IStrategy` or existing projection adapters is required.

The snippet assumes host-provided values: `$incumbent` is an independently trained
`ModelCandidate`; `$projected` and `$holdout` are `TrainingBatch` objects with the
same projection identity and disjoint training/holdout evidence; `$lifecycle` is a
`ModelLifecycle` owner initialized with the incumbent. `$hostPolicy->reviewTraining()`
and `$reviewActivation` must each return an explicit `GovernanceDecision`. The
[complete runnable example](../examples/generic/cortex/learn.php) constructs these
objects using synthetic evidence and approvals. Its approvals are fixture policy,
not a production authorization implementation.

## Evidence and pressure

`TrainingPolicy` defaults to a minimum of ten examples, ten new examples per unit
of sample pressure, one corrected example per unit of correction pressure, a
pressure threshold of one, and a maximum of 1,000 examples per batch.

Newness is determined by exact experience/outcome identity. Duplicate lineage in
a batch is rejected, and a previously learned identity cannot acquire different
sample or label data. Successful training accumulates lineage across windows;
dropping and later reintroducing old rows does not make them new. Projection id
and version must match the coordinator's initial training projection.

`TrainingTrigger` accepts optional normalized signals in `[0, 1]`: `novelty`,
`drift`, `failure`, `latency`, `cost`, and `training_cost`. These are host-admitted
pressure observations, not raw milliseconds, currency, confidence, or measurements
inferred by the coordinator. Missing signals remain absent; invalid, unknown,
nonfinite or coercible string values fail. Signal weights default to one and can
be configured from zero through 1,000.

Pressure is new count / minimum-new count, plus corrected count /
minimum-correction count, plus each weighted supplied signal; `training_cost`
subtracts instead of adding. The result is clamped to zero before the extension
filter. Corrected-outcome references must be unique exact
`{experience_id, outcome_id}` pairs belonging to new rows in the current batch.
The host is responsible for establishing that these observations are corrections;
the library does not infer semantic truth or replace old labels automatically.

An explicit operator request can bypass the pressure threshold. Neither it nor a
pressure filter can bypass the sample floor, batch ceiling, retention limits or
host approval. Approval must be an actual `GovernanceDecision` with the exact
`approved` status. Pending, denied, steered and unknown statuses do not invoke the
factory or trainer. Factory and trainer callbacks are trusted host code and own
resource enforcement, cancellation and any provider use.

## Results, retries and ownership

Results expose `deferred`, `denied`, `trained` or `uncertain`, with the request id,
exact candidate identity, training fingerprint, policy assessment, explicit review
decision and a sanitized failure class when needed. Elapsed milliseconds measure
the factory/trainer call; they do not interrupt it. Cost and energy remain null.
`candidate()` returns a model only for `trained`; `results()` retains terminal
result objects for inspection.

A request id binds candidate version, the complete projected batch and trigger.
Identical retries return the original result without repeating policy filters,
review, training or events. Different payloads for the same id fail. Deferred and
denied requests are terminal too: submit a new id when more evidence or a new
review becomes available. Callback identity is not part of replay binding; a
historical receipt is not a request to execute replacement callbacks.

Before construction, an approved attempt reserves the exact version. Reusing the
incumbent or any retained strategy instance is rejected before trainer invocation.
Object identity cannot detect shared mutable internals or callback mutation of
external objects: hosts must provide independent model state and preserve trained
instances. A failure in construction or training is uncertain, retains its receipt,
and stops all new training on that coordinator. Do not blindly retry through a new
id, version or owner; reconcile external effects first. There is no built-in
reconciliation, checkpoint restore or worker restart protocol in this slice.

The owner retains at most 1,000 request results and 10,000 learned evidence rows by
default, with no silent eviction. Capacity checks precede invocation, while existing
receipts remain replayable. All ownership and deduplication are synchronous and
process-local; durable jobs, authenticated artifacts and distributed coordination
remain open work.

## Hooks

When the host enables DevElation hooks, `automata.learning.pressure` filters the
nonnegative calculated pressure. The filtered value must remain a finite,
nonnegative number. The hard sample and authority gates apply afterward.

Actions use `automata.learning.training.` followed by `requested`, `started`,
`completed`, `failed`, `deferred` or `denied`. Each receives one detached array with
the request evidence and event name. `requested` follows successful eligibility
and retention checks; `started` follows approval. Terminal results are retained
before their event is emitted. Replay emits no additional events. Observer errors
are contained and available through `eventErrors()` as request id, event and error
class; they do not undo training. Hooks are synchronous and must remain fast and
observational. Approval and pressure-filter exceptions propagate before training.

## Observable proof

Run `php examples/generic/cortex/learn.php` from a checkout with dependencies
installed. Its 15 gates use the frozen synthetic corpus and real Naive Bayes,
Agent, governed fixture tool, response composer and TaskTrace implementations:

- Six observed examples defer training; twelve labelled observations train once.
- Denied training cannot construct a candidate; training alone cannot activate it.
- Independent held-out comparison and activation approval change a later Agent
  plan from `directions` to `checkin` for the same request.
- Model approval does not authorize the tool, and confirmation waits for a
  successful terminal delivery receipt.
- Rollback restores future plans; replaying training cannot reactivate a model.

The command emits JSON evidence and exits nonzero on a failed gate. Together with
[the other demos](../examples/generic/cortex/README.md), this supplies repeatable
contract evidence, not open-world quality or production recovery certification.
