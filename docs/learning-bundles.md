# Compatible learning bundles

`LearningBundle` binds versioned workflow, routing-policy and local-model artifacts
as one compatible set. Component kinds and slots are extensible; the library does
not prescribe a topology, feature signature, training algorithm or reward policy.
Training, evaluation, approval, publication and execution remain separate steps.

## Manifest and runtime boundary

A manifest has an `id`, `version`, `scope` and nonempty `components` list. Each
component declares `slot`, `kind`, `id`, `version`, an `artifact` record containing
an opaque `key`, `format` and SHA-256 `sha256`, and a `requires` list. Each dependency
names an exact `slot`, `id`, `version` and `sha256` within the same manifest.
Missing or mixed dependencies, duplicate slots and conflicting identities fail.

`BundleSnapshot::resolve($manifest, $readArtifact)` obtains raw bytes through a
host-supplied resolver and verifies every digest before returning any snapshot.
`artifact($slot)` then returns those captured bytes. Runtime adapters must consume
the snapshot rather than reopen mutable files. Hashes establish consistency, not
trust: the host authenticates artifact sources, authorizes formats and supplies
safe decoding and executable bindings. A manifest never grants execution authority.

## Publish and recover

Create `BundleLifecycle($store, $scope, maximumRequests: 1000)` around an
`IBundleStore`. Explicit configuration calls `initialize($anchor, $readArtifact)`
only on an empty store. The anchor and subsequent history remain retained.

`activate($requestId, $expectedRevision, $candidate, $evaluate, $authorize,
$readArtifact)` verifies incumbent and candidate bytes, then calls the evaluator
with both snapshots. Its result must contain a Boolean `accepted` and an `evidence`
array. Rejection records a receipt without asking for authorization. Acceptance
requires a separate `GovernanceDecision::approved()` before publication. Hosts
must define frozen holdouts, regression budgets, prior-capability checks and
minimum evidence appropriate to their workloads; approval alone measures none
of these. Evaluator and authorizer callbacks must be synchronous and have no
external side effects because another writer may win the final store comparison.

One compare-and-swap publishes the complete active set, revision and receipt.
Identical request replay returns the retained receipt without resolving artifacts,
evaluating, authorizing or publishing again. Changed bindings under an existing
request id and stale revisions fail. Rejected requests also consume retention
capacity; capacity exhaustion blocks new requests rather than silently removing
replay protection. `receipts()` preserves rejected and accepted transitions.

`rollback($requestId, $expectedRevision, $authorize, $readArtifact)` verifies and
restores the previous complete set with explicit approval. It advances the revision
without erasing activation history. Unavailable previous artifacts prevent rollback.

Any store write exception makes that lifecycle instance uncertain. Further reads
and writes fail until `recover($readArtifact)` reads authoritative state and returns
`committed` or `not_committed` for the pending attempt. Recovery never repeats the
write. A different transition under the pending request id requires explicit
reconciliation. A fresh instance validates persisted history before use; after a
lost acknowledgement, resubmit the exact request to recover its receipt.

## Store contract and qualification

`IBundleStore::load()` returns a detached authoritative complete state or `null`.
`compareAndSwap($expectedDigest, $replacement)` compares the canonical
`RecordSnapshot::fingerprint()` of the existing state, or `null` for an empty store.
It atomically publishes the entire replacement or rejects it. An exception may
mean the acknowledgement was lost after commit. A recovery read must resolve that
uncertainty; the store must not allow an earlier write to arrive after that read.
Adapters own integrity, access control, concurrency and retention guarantees.

`FileBundleStore` is a local reference using a private existing directory, a stable
cooperative file lock and immutable complete generations. It flushes prepared bytes
before renaming a new generation into place. Reads verify the entire generation
chain and ignore unpublished preparations; missing middle records, malformed
commits or digest mismatches fail closed. Configurable byte, generation and lock
wait bounds prevent unbounded operation. JSON must round-trip without changing scalar values;
lossy `serialize_precision` settings fail before publication without changing PHP
configuration. Native filesystem calls are isolated at this integrity boundary
because ordinary DevElation `Disk` writes do not offer
atomic compare-and-swap publication; ordinary data transformations use DevElation.

The demonstrated guarantee is local process interruption and restart, with one
publisher at a time. Directory synchronization for power loss, network filesystem
semantics, distributed leases, hostile removal of a valid generation tail and
artifact garbage collection are not qualified. Keep the directory and immutable
artifact catalogue host-owned. Stronger stores can implement the same port.

## Runnable evidence

Run `php examples/generic/cortex/bundle_restart.php`. Seven actual PHP processes
exercise a routed two-node graph, an anchored policy and a fitted decision tree.
Observed baseline sessions receive explicit negative fixture feedback. A separately
trained candidate tree and adjusted routing policy are evaluated together with the
candidate workflow on two frozen held-out inputs: correct outcomes improve from
1/2 to 2/2, preserving the original capability. The fixture then terminates before
and after publication, replays the lost acknowledgement, rolls back and restarts.
All 12 checks must pass, and CI runs the example without credentials or services.

This is a narrow synthetic compatibility and recovery proof. It does not establish
statistical generalization, distributed coordinated training, universal assignment
of workflow rewards to model labels, or complete agent-session recovery. See
[session learning](session-learning.md) for evidence admission and model-specific
restart probes, and [model lifecycle](model-lifecycle.md) for the separate
process-local model-reference controller.
