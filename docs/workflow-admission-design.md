## Conditional API review incorporation — bounded read admission

**Disposition:** the independent review's eight conditions are incorporated in the proposed public contract below. This resolves the previously unanswered design choices; it is not implementation approval, PR approval, a release contract or proof that the new behavior exists. The existing alpha.9 overlap API and its baseline tests remain unchanged.

### Proposed public types and additive entrypoints

| Surface | Contract |
| --- | --- |
| `WorkflowAdmissionPolicy` | Final immutable configuration: schema/policy version and strictly positive integer `maximumInFlight`. Detached data-only export; no clock, callable, reservation or mutable execution state. |
| `CompositeStrategy::startAdmitted(...)` | New opt-in entrypoint accepting input, policy, runtime-only monotonic clock, optional runtime deadline, run ID and context key. Creates an admitted `StrategyWorkflowRun`. Existing constructor, `start()` and serial `predict()` signatures/defaults remain untouched. |
| `StrategyWorkflowRun::admitted(...)` | Additive factory for direct run construction using the same policy/runtime binding. Existing constructor stays unchanged. Only this run owns graph state, reservations, attempt identities and receipts. |
| `StrategyDispatchContext` | Final detached snapshot of run ID, node/task ID, scheduling-attempt ID, candidate ID/version, capability/subject, request digest, context/policy revision and reservation token. No live run, adapter or reference-bearing input is exposed. |
| `IStrategyDispatchGuard::check(StrategyDispatchContext): StrategyDispatchDecision` | Deny-only, synchronous final veto. A typed allow removes only this extra veto; it cannot supply authority or bypass router policy. |
| `StrategyDispatchDecision` | Final immutable allowed/code result with validated stable codes. Invalid values, exceptions or suspension become a not-invoked guard denial. |
| `StrategyRouter::routeGuarded(...)` | New opt-in entrypoint sharing the core route algorithm. Existing `route(request, authorization, trace)` and its named parameters remain unchanged. Guarded execution is an explicit new extension point, not a silent bypass of custom subclasses' route behavior. |
| `WorkflowAdmissionReceipt` | Versioned detached append-only observation with reservation identity, node namespace, dependency provenance, request/context digests, invocation flag, stable outcome code and late/cancel/deadline observations. No executable configuration or clock object. |

The runtime clock and deadline are supplied separately from exported policy data. They are neither serialized nor hashed into workflow/policy identity, replayed, or published as interoperable clock state. Clock reads use one run-local monotonic epoch; invalid or decreasing readings fail closed. `now >= deadline` denies. Clock and final guard must return synchronously: the implementation must detect a yielded final evaluation and deny without resuming it or invoking the adapter. No potentially suspending callback may run between the final allow and adapter invocation.

The opt-in result may expose admission observations and counters. Unconfigured result bytes/shape and behavior remain unchanged for equivalent deterministic inputs; compatibility tests must cover legacy positional/named calls and subclass overrides. Existing `route()` overrides continue to govern legacy calls. A custom router must explicitly support the guarded extension before an admitted run can use it; do not imply that an inherited guarded method preserves arbitrary custom `route()` overrides.

### Reservation, attempt and invocation accounting

- Reserve atomically by immutable run/node/scheduling-attempt/reservation identity **before authorization** or any other callback. Atomic means no cooperative suspension in the local state transition, not a distributed lock.
- `ready()` respects available slots. Direct `execute()` applies the same check; it cannot bypass admission.
- Capacity refusal leaves the node pending, calls no authorization, allocates no scheduling attempt and consumes no execution retry. Return/record a stable `capacity_unavailable` admission outcome; preserve the existing `execute(): self` signature. This is not a failed-node attempt.
- A scheduling attempt identifies one admitted route operation. An execution-attempt counter increments only when that operation first actually invokes an adapter. A separate adapter-invocation counter increments for each primary/retry/fallback candidate actually called. Guard denial adds no invocation or retry consumption; it never erases earlier real invocations in the same route operation.
- Keep a slot through suspended authorization, advice, eligibility, estimation and invocation. Release it exactly once when that admitted attempt settles, including a returned terminal uncertainty result.
- Known-failure retry budgets use actual execution attempts, not capacity refusals or pre-invocation denials. Total scheduling bounds remain finite and separately named. No uncertainty, cancellation, expiry or guard denial is eligible for failure-edge retry/fallback.

### Receipt state machine

| Observation/transition | Adapter invoked? | Capacity and terminality | Next action |
| --- | --- | --- | --- |
| Pending, capacity full | No | No reservation; node remains pending | May become ready after another attempt settles |
| Reserved, suspended in authorization/advice/eligibility/estimate | No | Slot held; attempt nonterminal | Host resumes the existing Fiber; no new duplicate attempt |
| Final guard denies or fails | No for this candidate | Append `executed=false` terminal denial; release when route operation returns | Stop route, no retry/fallback from denial; skip unsatisfied dependents |
| Cancel/expiry before invocation | No | Close new admission; resumed preflight records `dispatch_cancelled`/`dispatch_expired`, then settles | Zero new adapter calls; dependent skip |
| Adapter invoked and suspended | Yes | Slot held; attempt nonterminal and run `settled=false` | Host resumes/polls existing handle; never invent completion |
| Adapter returns completed/known failed | Yes | Append terminal receipt, release slot | Preserve declared success/failure edges and existing typed known-failure retries |
| Adapter returns terminal uncertainty | Yes | Append terminal uncertainty; release that slot | Never retry; other suspended workers still hold slots and keep run unsettled |
| Adapter returns after cancel/expiry | Yes | Append terminal receipt with late/cancel/deadline observations; release slot | Do not reopen admission or rewrite the earlier cancellation decision |
| Later external reconciliation of a settled result | Not a new invocation | Separate append-only evidence referencing original receipt | Never mutate the terminal decision or silently retry |

Not-yet-invoked denials carry stable distinct codes (`dispatch_cancelled`, `dispatch_expired`, `dispatch_guard_denied`, `dispatch_guard_invalid`, `dispatch_guard_exception`, `dispatch_guard_suspended`). Candidate-level `executed=false` is truthful even if an earlier candidate ran; aggregate counters retain that history. Returned uncertainty settles the local attempt; unresolved suspended work does not. Run decision and `settled` remain separate. A terminal decision is not permission to release another worker's slot or claim the entire run completed.

Inputs, outputs and dependency snapshots remain detached per task/attempt. Required joins depend on settled prerequisite receipts and use declaration/stable-ID ordering, never completion order. Failed/cancelled/expired prerequisites skip dependents deterministically, while explicitly declared known-failure edges retain their existing meaning.

### Condition-to-test obligations

1. Immutable policy validation, runtime-only clock exclusion, deadline equality, legacy constructors/named arguments/subclass behavior.
2. Three reads at limits 1/2, suspended authorization occupies capacity, no authorization/retry consumption on capacity refusal, direct-execute bypass rejection.
3. Separately suspend advice, eligibility and estimate; cancel/expire before resume; assert zero adapter calls. Test forbidden clock/guard suspension and no gap between final allow and invocation.
4. Append-only not-invoked receipts and independent scheduling/execution/invocation counters, including denial after an earlier fallback candidate actually ran.
5. Reference-mutation attacks, namespaced provenance/digests and deterministic required joins under reversed completion order.
6. Returned uncertainty releases capacity without retry; a suspended invocation remains in flight; late evidence cannot rewrite terminal receipts.
7. Cancellation/expiry closes admission, preserves actual late invocation results and skips dependent work.
8. Guard every primary, known-failure retry and fallback invocation; fake clocks/Fiber checkpoints only, no elapsed-time thresholds. Keep all no-policy regressions green.

This is one local cooperative state owner, not a new graph scheduler, general effect executor, hard-interruption runtime or durable/distributed worker system. A handler's name does not establish side-effect freedom. Synematic Pipeline/Graph remain sequential task boundaries; no concurrency reinterpretation or downstream adoption is implied.

### Gate disposition

- Ownership and independent conditional design review: received; no unanswered reviewer/ownership request remains.
- Incorporation into proposed public types and receipt state machine: complete in this specification, with executable obligations above.
- Implementation/test slice: not yet started or approved by the review receipt. Implementation follows a separately reviewed, tests-first change; this specification does not assert runtime support.
- Implementation correctness, independent PR review, exact-head landing, dependency changes and release: unproved/not authorized by this design disposition.
