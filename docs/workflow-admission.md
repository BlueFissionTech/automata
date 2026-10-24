# Bounded cooperative read admission

This opt-in API adds an in-flight bound and final invocation veto to the existing
`StrategyWorkflowRun`. It is not a new dependency scheduler or effect executor.
Unconfigured constructors, `start()`, serial `predict()` and `StrategyRouter::route()`
retain their signatures and result shape.

```php
use BlueFission\Automata\Strategy\Workflow\WorkflowAdmissionPolicy;

$run = $composite->startAdmitted(
    input: $input,
    policy: new WorkflowAdmissionPolicy(2),
    clock: $monotonicNanosecondClock,
    deadlineNanoseconds: $deadline,
    runId: 'request-42',
    contextKey: 'reviewed-context',
);
```

The host starts/resumes Fibers for eligible nodes from `ready()` using `execute()`.
Direct calls enforce the same bound. A capacity refusal leaves a node pending,
does not call authorization or allocate an attempt, and records
`admission.last_outcome.code = capacity_unavailable`. A suspended authorization
or invocation occupies a slot until the attempt returns or unwinds.

The policy is immutable data-only configuration. Clock/deadline bindings are
runtime-only and absent from exported plans, policies, fingerprints and results.
Supply nonnegative integer readings from one monotonic epoch. Equality expires;
invalid, decreasing or suspended clock evaluation fails closed. Clock and guard
callbacks must be pure; nested Fiber evaluation detects forbidden suspension,
but does not sandbox arbitrary host code or hard-interrupt a blocking callback.

`StrategyRouter::routeGuarded()` applies an `IStrategyDispatchGuard` immediately
before every adapter call, after advice, eligibility and estimation. Context is a
detached identity snapshot. Guards are deny-only, synchronous and must return a
`StrategyDispatchDecision`; invalid/exceptional/suspended results cannot invoke
or trigger fallback. Mutation of the request during preflight also denies.
Custom routers must explicitly opt in through `supportsGuardedExecution()`;
an inherited guarded entrypoint must not silently replace custom legacy routing.

Admitted results add `admission` with policy, in-flight reservations, scheduling
attempts, execution attempts and actual adapter invocations. An execution attempt
counts only after the route operation first invokes an adapter; fallback adds an
invocation, not another scheduling attempt. Per-node attempt receipts include
`executed` and detached admission provenance. Guard denial adds no invocation,
and never erases a prior candidate's actual work. Retry budgets count execution
attempts, not capacity refusals or pre-invocation denials.

`cancel()` and expiry close admission, not in-flight I/O. An invoked worker may
return late; its receipt retains that observation. A returned terminal uncertainty
settles/releases that attempt and is never retried. Another suspended worker
still keeps the run `settled=false`. Do not equate a terminal decision with all
workers being settled. Snapshots cannot modify prior receipts; any external
reconciliation is separate appended host evidence, not a retry instruction.

Inputs/predecessors and receipts are detached; required joins use declared order.
Explicit known-failure edges/retries retain their meanings. Whole-run fail-fast
and strict unknown `Orchestrator` modes are not implemented by this slice.
Synematic Pipeline/Graph remain sequential task boundaries. Handler names are
not evidence of side-effect freedom: initialization and migrations are effects.

## Provider-free verification

```sh
php vendor/bin/phpunit --do-not-cache-result tests/Automata/Strategy/Workflow/WorkflowAdmissionTest.php
php vendor/bin/phpunit --do-not-cache-result tests/Automata/Strategy/Workflow/StrategyWorkflowTest.php
php vendor/bin/phpunit --do-not-cache-result tests/Automata/Strategy/StrategyRouterTest.php
php examples/generic/workflow_admission.php
```

Tests use fake clocks and explicit Fiber checkpoints, not elapsed-time thresholds.
The example demonstrates three reads under a bound of two, authorization occupying
capacity, a terminal join and expiry at equality. This is single-owner cooperative
execution, not CPU parallelism, durable/distributed scheduling or live-provider
qualification. Hosts own handler classification, authorization and transport.
