# Model activation and rollback

`ModelLifecycle` owns an active `ModelCandidate` reference and a monotonic revision.
It compares a candidate to the current incumbent using `ClassificationEvaluator`,
then asks a trusted host for a `GovernanceDecision`. It accepts only the exact
`approved` status. Pending, denied and steered decisions leave the model unchanged;
steering needs a new candidate and a new evaluation.

```php
use BlueFission\Automata\Learning\ClassificationEvaluator;
use BlueFission\Automata\Learning\ModelLifecycle;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;

$lifecycle = new ModelLifecycle($incumbent, new ClassificationEvaluator());
$receipt = $lifecycle->promote('review-42', $lifecycle->revision(), $candidate, $holdout,
    static fn (array $transition): GovernanceDecision => $hostPolicy->review($transition));
// Inference explicitly uses this owner; unrelated routers or registries are not mutated.
$prediction = $lifecycle->active()->strategy()->predict($input);
$rollback = $lifecycle->rollback('rollback-42', $lifecycle->revision(),
    static fn (array $transition): GovernanceDecision => $hostPolicy->review($transition));
```

The callback receives `request_id`, operation, expected revision, exact from/to
identities and the measured evaluation report (null for rollback). It must perform
current host policy checks and return an explicit decision. Receipts retain that
transition, decision status/message/payload, applied flag, rejection reason and
resulting revision. Put any review correlation in the decision payload. Library
approval of a model reference does not authorize tools, provider spend or deployment.

Each request id binds the operation, expected revision, exact candidate object and
holdout fingerprint. Identical retries return the stored historical receipt without
prediction, approval or activation. This remains true after rollback: a previously
applied receipt does not describe the current active model. Read `active()` and
`revision()` for current state. A denied or pending receipt is also final for its
request id; retry review with a new id and the current revision. Conflicting ids,
stale revisions and reentrant mutations throw before invocation. Invalid evidence
or throwing/malformed authorization leaves state unchanged and produces no receipt.

The default bound is 1,000 retained requests, including rejected transitions. There
is no eviction that could silently permit a duplicate. At capacity, new requests
fail; existing receipts remain replayable. Rollback returns to the immediately
previous activation; repeated rollbacks traverse the activation stack. Model
versions remain bound to their original objects, even after rejection or rollback.

This is synchronous, process-local ownership. Hosts must preserve immutable model
instances, side-effect-free prediction and approval callbacks, trusted training
lineage and labels. Object binding cannot detect mutation inside a caller-owned
strategy. There is no model serialization, checkpoint restoration, cross-process
lock, artifact signature or deployment transaction. Creating a new owner loses
its request history; do not treat that as durable retry recovery.

## Executable regression proof

Run `php examples/generic/cortex/run.php` and
`php examples/generic/cortex/promote.php`. The foundation demo projects 12 annotated
experiences, excludes a pending experience, checks exact lineage and all six held-out
predictions. The promotion demo changes actual inference, rejects a regression,
rolls back, replays historical receipts without activation, and rejects new requests
with stale revisions. These run in CI alongside the other Cortex
demos; missing required contracts or files fail execution, with no optional skip.

`fixture-v1.json` contains the versioned synthetic corpus. `baseline-v1.json` pins
its SHA-256, projection, lineage and expected per-case predictions. The digest is
over decoded/re-encoded compact JSON with unescaped slashes; whitespace and checkout
line endings are irrelevant, while field/list order and data changes are significant.
The same comparison rejects a constant prediction set and a set with only one wrong
prediction. These controls validate the regression gate, not statistical confidence.

Fixture or expected-label changes require explicit review with a new version and
an explanation of affected cases. Do not refresh the baseline merely to make a
failing implementation pass. The small synthetic corpus is reproducible evidence
of library behavior, not a production quality, immutable artifact or downstream
compatibility certification.
