# Progressive response composition

`ResponseEnvelope`, `ResponseFragment`, `ResponsePolicy` and `ResponseComposer`
compose independently produced output through a single-writer state machine.
Channels are caller-owned nonempty identifiers such as text, audio, tool or state.
The composer prepares data; the host delivers it through its governed executors.

## Production and policy

Declare the fixed fragment list before production. Each fragment has an id,
channel, nonnegative finite weight, integer priority, blocking flag, dependency
ids, optional production deadline, optional nonblocking fallback and trace data.
Envelopes accept at most 128 unique fragments, require positive finite total
weight and reject missing dependencies and cycles. Membership and weights cannot
change after construction.

`progress(id, fraction)`, `resolve(id, payload, confidence)` and `fail(id, reason)`
return the composer for fluent composition. Progress is monotonic; reporting
progress alone does not make a payload ready. Resolution freezes its payload.
Unknown confidence remains null; supplied confidence must be a finite number
between zero and one. Plain snapshots preserve false, zero and null, detach PHP
references, and reject runtime objects, resources, nonfinite numbers and excessive
depth. Learning records share the internal snapshot validator without changing
existing record schemas.

Completion is the sum of weight times completion divided by total weight.
`ResponsePolicy` has a threshold and an explicit minimum threshold; both default
to one. The threshold cannot fall below the minimum. Every blocking fragment must
resolve successfully, regardless of percentage. A blocking flag is a readiness
requirement, never a grant of execution authority.

## Preparation and terminal receipts

`prepare(nowMs)` returns a stable release record or null. Ready fragments are
ordered by descending priority, preserving declaration order for equal priorities.
Dependencies must already have successful receiver receipts for their primary
payloads. Fragments that depend on one another are therefore released in separate
acknowledged waves. A prepared action alone cannot unlock its confirmation.

Persist the checkpoint before delivery. The receiver must deduplicate the release
id and its fragment ids across retries and retain the original terminal results.
Then call `acknowledge(releaseId, receipts)` with a complete map:

```php
[
    'fragment-id' => [
        'successful' => true,
        'evidence' => ['receiver_receipt' => 'trusted-result-reference'],
    ],
]
```

A receipt describes completed receiver processing, not queue acceptance or an
unknown attempt. False results are terminal failures and cannot unlock dependent
confirmations. Every fragment requires an explicit boolean result and evidence
array; the library validates shape, while the host authenticates evidence.
Identical acknowledgements return false. Conflicting results are rejected.
Prepared output retains the same identity and payload until acknowledged.

After an unknown delivery outcome, reconcile with the receiver by the existing
id. Do not interpret an observation timeout as permission to repeat an effect.
For a multi-fragment release, the receiver must also handle partial processing
and preserve per-fragment deduplication; composer state alone cannot guarantee
exactly-once external effects.

## Checkpoints, time and cancellation

`snapshot()` returns schema version one with contracts and transition history;
`ResponseComposer::restore()` validates and replays it. Encode JSON with
`JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION`. Use round-trip numeric precision when encoding checkpoints. Delivery fingerprints
encode floating-point bits directly and do not depend on PHP serialization precision.
Object key order does not
change delivery identity, but list order and scalar types remain significant.
Checkpoints are trusted host records, not cryptographic proof or authorization.
Storage durability, atomic checkpoint/receipt writes, locks and concurrent worker
serialization remain host responsibilities.

The history allows 10,000 ordinary transitions and reserves terminal capacity for
acknowledgement and cancellation. At capacity, reconcile and cancel before
starting a new response. Snapshot reads do not consume history.

Deadlines and `prepare(nowMs)` use the same caller-supplied nonnegative monotonic
millisecond timeline. Tick before admitting late worker results. Deadlines apply
to pending production and do not interrupt a running worker or expire already
prepared deliveries. Failure or timeout may select predeclared nonblocking
fallback content. Fallback delivery never proves that the original operation
succeeded and cannot satisfy a dependency on that operation. Blocking fragments
cannot declare fallbacks.

`cancel(reason)` prevents new output and late production. It cancels unfinished
fragments while preserving prepared deliveries for explicit reconciliation.
Already delivered effects are not undone; a receipt can still acknowledge an
in-flight batch after cancellation. Hosts propagate cancellation to their actual
workers and executors.

## Observable example and remaining integration

Run:

```sh
vendor/bin/phpunit --do-not-cache-result tests/Automata/Response
php examples/generic/cortex/respond.php
```

The example uses the existing trained classifier, emits an early response,
prepares a simulated service request, restores after a lost acknowledgement,
reuses the receiver receipt and emits confirmation afterward. Separate probes
exercise cancellation and deadline fallback. Its receiver state is deliberately
retained in memory across the simulated caller restart. This demonstrates the
protocol with synthetic effects, not durable production delivery.

## Agent and worker integration

`Agent::startResponse($envelope, $policy)` returns an `AgentResponse` handle bound
to the Agent's current session and TaskTrace. It exposes the composer's fluent
production methods, preparation, acknowledgement and cancellation. Its
`worker($fragmentId, $producer)` wrapper fits the existing orchestrator. Producers
explicitly return `['status' => 'completed', 'output' => $value]` or a `failed`
result; optional confidence must satisfy the composer contract and otherwise
stays null. An explicit unknown worker confidence also keeps the orchestration
aggregate unknown; known numeric zero remains a measurement. Raw strings, partial statuses and missing output are rejected. A
failed result or exception fails the fragment; it does not prove receiver success.

```php
$response = $agent->startResponse($envelope);
$agent->configureOrchestration(['workers' => [
    'format' => $response->worker('text', fn () => [
        'status' => 'completed', 'output' => 'A deterministic response.',
    ]),
]]);
$agent->orchestrate();
$release = $response->prepare(0);
```

The handle checks its session/task binding before and after producer invocation.
Duplicate, unknown, cancelled and exhausted fragments are rejected before work.
A producer can cancel the response; its late result is discarded. Producers are
synchronous and trusted computation: this adapter neither interrupts them nor
authorizes any tools they might call. Use the existing governed executors for
effects, with receiver-side idempotency and current host permission checks.

`Agent::restoreResponse($checkpoint)` accepts handle schema version one and requires
the same session and task ids. Checkpoints are allowed only between producer calls;
they do not record an in-progress worker invocation or provide safe worker restart.
The handle must stay bound to the current TaskTrace object during execution. Hosts
persist traces separately and authenticate checkpoint origin, tenant/actor scope,
receiver identity, version compatibility and revocation before restoration.
Session/task equality alone is not an access-control decision.

Response trace events carry response, session, fragment and release ids. Prepare
events mean prepared output, not completed execution; acknowledgement is a separate
event with the aggregate terminal result. Repeated prepare/acknowledge/cancel calls
do not add duplicate transition observations. Response events omit content payloads;
the existing orchestration and tool tracing may still capture their own payloads.
Observer exceptions are contained after response transitions and exposed through
`telemetryErrors()` (at most 32 entries, process-local). They never grant retry
permission or erase committed response state.

Run `php examples/generic/cortex/agent.php` for the integrated proof. It combines
the trained classifier, real Agent orchestration and tool approval, progressive
output, TaskTrace and checkpoint restoration. Neutral host fixtures reject tenant,
actor, receiver and version mismatches, refresh permission after preparation, keep
terminal receipts after cancellation and refuse to retry a tool whose effect may
have completed before an exception. Tool retries are explicitly disabled in this
fixture. Its scope checks and retained in-memory ledger are example host code,
not a production authorization, storage or concurrency service.

These APIs require PHP 8.2+ and the declared DevElation dependency. They are staged
source contracts and have no released package version yet. Concurrent transactional
persistence, authenticated receipt adapters and interruption recovery remain open.
No provider calls, dependency changes or release promotion are required.
