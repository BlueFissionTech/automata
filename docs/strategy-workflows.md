# Composed strategy workflows

`CompositeStrategy` implements `IStrategy` and can be registered with ordinary
`Intelligence::registerStrategy()`. A workflow is a versioned proposal captured
from existing `Path\Graph` and `Path\Node` objects. A run executes each selected
node through `StrategyRouter` with fresh host authorization. These APIs are staged
development contracts, not a published release claim.

## Declare a plan

```php
use BlueFission\Automata\Path\{Graph, Node};
use BlueFission\Automata\Strategy\CompositeStrategy;
use BlueFission\Automata\Strategy\Workflow\StrategyWorkflow;

$graph = new Graph();
foreach (['classify', 'answer'] as $id) {
    $graph->addNode(new Node($id, null, [], ['workflow' => [
        'strategy_id' => $id,
        'strategy_version' => '1',
        'capability_id' => 'infer',
        'capability_version' => '1',
    ]]));
}
$graph->connect('classify', 'answer');
$plan = new StrategyWorkflow('intent-response', '1', $graph, ['answer']);
$strategy = new CompositeStrategy($plan, $router, $authorize, 'actor-42');
$outputs = $strategy->predict('confirm booking'); // ['answer' => node output]
```

The host supplies `$router` with exact registered `IStrategyRouteAdapter` bindings.
`$authorize` receives a detached request array and must return `AutonomyDecision`
with the correct subject, capability and version. The adapter still passes router
availability, allowed-mode, side-effect-free, eligibility and limit checks. A plan
also requires the adapter's current identity to match its exact requested id/version;
an ambiguous registration key or changed advertised version fails closed. A plan
cannot register an adapter or grant permission. Authorization errors and malformed
decisions deny that node without invoking its adapter.

Plans allow 1–128 nodes and at most 512 edges. Node names start with an ASCII letter
and contain letters, digits, dots, underscores or hyphens, up to 128 characters.
Cycles, missing dependencies, duplicate imported nodes/edges and unknown fields
are rejected. Graph changes after construction cannot alter the captured plan.

| Node metadata under `workflow` | Contract |
| --- | --- |
| `strategy_id`, `strategy_version` | Exact adapter identity; both required |
| `capability_id`, `capability_version` | Exact requested capability; both required |
| `allowed_modes` | Nonempty list of `deterministic`, `learned`, `generative`; defaults to deterministic only |
| `limits` | Optional finite nonnegative router limits: `max_cost`, `max_latency_ms`, `max_energy`, `max_invocations` |
| `join` | `all` by default; `any` permits dispatch once one incoming edge matches |
| `maximum_attempts` | Integer 1–8, default 1 |
| `retry_codes` | Explicit adapter failure codes eligible for another attempt; default empty |

An edge defaults to `on: completed`. Set `on: failed` for fallback after a declared
terminal adapter failure. Optional `when: {path: [...], equals: value}` compares an
output path using strict PHP equality. An empty path compares the whole output;
a missing key never matches, even when the expected value is null. No executable
condition code is stored in a plan.

Each adapter receives normalized input containing `root` (the detached original
input) and `predecessors` (matching terminal parents keyed by node id, with status,
code and output). Unfinished, skipped and nonmatching parents are absent. `all`
joins require every incoming edge to match; `any` joins capture the matching parents
available when dispatched. Values must be plain finite scalar/array data. Runtime
objects and resources fail validation; payload depth reserves eight levels within
the 32-level record limit for enclosing requests and receipts.

## Schedule and observe execution

`predict()` schedules eligible nodes serially and returns a map of selected output
ids to values. It throws if the run does not complete; inspect `lastResult()` for
the retained result. `accuracy()` has no measured value and throws; `Intelligence`
already treats unavailable accuracy as unknown. `train()`, `saveModel()` and
`loadModel()` explicitly reject unsupported operations. Train independent node
models and evaluate composed output separately; these methods do not simulate
workflow learning or silently serialize executable callbacks.

For overlapping workers, obtain a run with `start($input, $runId, $contextKey)`.
The host schedules one `execute($nodeId)` call for each selected id in `ready()`.
`execute()` and `cancel()` return the run for fluent use. A running or terminal
node cannot be dispatched twice. A failed node appears again only while its
declared retry policy and the run dispatch budget permit it. Every attempt gets
a new request identity and fresh authorization.

```php
$run = $strategy->start($input, 'request-42', 'intent');
$workers = [];
foreach ($run->ready() as $nodeId) {
    $worker = new Fiber(fn () => $run->execute($nodeId));
    $workers[] = $worker;
    $worker->start();
}
// The host event loop resumes suspended workers as their operations become ready,
// then checks ready() again for newly eligible dependencies.
$observation = $run->result()->toArray();
```

Fibers overlap only when adapters or policy callbacks yield; they do not create
CPU parallelism. Automata supplies execution state, while the host supplies the
event loop and its I/O, cancellation and resource controls. This run is a
single-owner object, not a cross-process queue or concurrent-write protocol.

The default run budget is 256 dispatches, configurable from 1 through 1,024.
Authorization denials count as dispatches. This bounds scheduling attempts, not
provider calls, nested workflows, elapsed time or spend. Node router limits retain
the existing router semantics; unknown hosted billing and shared reservations
remain open under [issue #116](https://github.com/BlueFissionTech/automata/issues/116).
Aggregate cost, energy and confidence remain null. Per-attempt elapsed milliseconds
include time suspended and do not interrupt execution.

## Completion, fallback and cancellation

The output list names possible results. By default every listed output must
complete; `minimumOutputs` can select a smaller positive count. A threshold of
one provides a first-completed-output race. Once the threshold is met, selected
outputs are frozen and undispatched work is skipped. Late results remain in node
receipts and cannot replace the winner or unlock more nodes.

`result()` returns a detached snapshot with workflow identity/fingerprint, run and
subject ids, context key, dispatch count, selected outputs and node attempt history.
Attempts retain the normalized input fingerprint and detached authorization decision;
later mutation of the host decision cannot rewrite recorded evidence.
Status is `running`, `completed`, `exhausted`, `cancelled` or `uncertain`. A terminal
status may still have workers in flight; `settled` becomes true only when the run
is terminal and all dispatched calls have returned. `ready()` and `result()` also
settle impossible dependencies. Router requests carry workflow/node metadata,
correlation id and optional TaskTrace identity.

Denial never matches a failure edge. Execution exceptions, malformed results and
actual-budget overruns close the run as uncertain, suppressing automatic retries
and fallback. A late uncertain result can change a completed run's status to
uncertain while retaining the already selected outputs and earlier snapshots.
Known adapter failure codes can retry or fall back only under declared policy and
fresh host authorization; declaring a failure does not prove there was no charge.

`cancel()` stops new dispatch and rechecks the run after authorization returns,
including a suspended authorization callback. It does not interrupt an adapter
already routed, erase its receipt or revoke completed outputs. Hosts must propagate
cancellation to actual workers and reconcile uncertain effects. Reusing a run id
in a new run is a new execution: ids provide correlation, not durable deduplication.

## Persist proposals and validate behavior

Export `StrategyWorkflow::toArray()` and restore with `StrategyWorkflow::fromArray()`.
The schema-1 record contains topology and exact versions. Preserve floating-point
types with `JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION`. Imports validate the
exported shape and reject transformations by graph hooks. These records contain no
model instances, grants, closures or live worker state; host admission is still
required before binding an imported plan. Result snapshots have no resume API.

Run `php examples/generic/cortex/workflow.php`. It demonstrates real Naive Bayes
classification through the router, two overlapping Fiber workers, fan-in, ordinary
Intelligence selection, scope denial, cancellation and TaskTrace. Unit tests add
conditional joins, fallback/retry, race/threshold completion, stale versions,
revocation, malformed records, exact false/zero outputs and late uncertainty.
All data and authorization in the demo are synthetic; no provider calls or live
effects occur. Durable recovery, shared nested budgets, learned route construction
and reinforcement, script strategies and goal graph integration remain open.
