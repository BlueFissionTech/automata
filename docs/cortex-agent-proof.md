# Cortex agent outcome experiments

Run the bounded scenario suite from a source checkout:

```sh
php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Automata/Cortex
php examples/generic/cortex/scenarios.php
php examples/generic/cortex/confidence.php
php examples/generic/cortex/replay.php
php examples/generic/cortex/prerequisites.php
```

The examples use actual `StrategyRouter`, `Intelligence`, `StrategyWorkflow`,
`CompositeStrategy`, Agent tools and response composition, and the learning
coordinator/model lifecycle. Scenario hosts supply synthetic worlds, current
authorization and outcome oracles. They do not replace the library scheduler.

## Acceptance rubric

| Scenario | Required observable outcome | Failure controls |
| --- | --- | --- |
| Conversation | Ask for missing booking evidence; block the dependent answer; cancel the old plan after a changed constraint; admit a replacement graph and answer from current facts | Wrong intent and stale reference fail the same strict evaluator |
| Operations | Replace an obsolete resource plan; refresh tool approval; restore the identical pending release after lost acknowledgement; apply an effect once | Revoked approval cannot produce success; unknown effects remain pending and cannot replay; success without a ledger entry is rejected |
| Game player | Improve win rate by at least 20 percentage points on paired held-out episodes, with no illegal moves | Independently replay every transition; reject an illegal move and an invented winner |
| Interactive NPC | Wait for player input; interrupt a trade and complete an escort quest; keep coins, inventory, location and confirmation consistent | A late trade answer cannot purchase; invented items and premature completion fail the world-state oracle |
| Continuous learning | Two admitted correction batches train isolated candidates; new examples improve held-out predictions; activation needs separate approval | Insufficient new evidence defers training; denied activation has no effect; replay cannot retrain; the earlier model remains unchanged |
| Confidence | Preserve absent or null scores through nesting and aggregation; trigger fallback only on measured low scores | Reject nonnumeric, nonfinite and out-of-range scores; never infer certainty from completion |
| Prerequisites and verification | Stop ordered dependent work on a non-completed prerequisite; preserve nested status; accept only Boolean verification | Failed/uncertain receipts cannot enable dependents; textual false cannot certify; uncertain work cannot implicitly retry |

The scenario suite reports 38 checks. The separate replay command
replays observed provider mistakes and measures five additional conditions for
each of two providers, using the committed sanitized observation fixture.
`php examples/generic/cortex/confidence.php` reports seven confidence gates.
`php examples/generic/cortex/prerequisites.php` reports eleven stop/verification gates.
All thresholds and splits are declared in source before fitting. A boolean
`passed` field from a producer is never sufficient evidence.

## Game benchmark

Normal play removes one to three stones per turn; taking the last stone wins.
Two fixed candidate policies are declared: take one, or leave a multiple of four.
The opponent follows a fixed seeded schedule. Training explores both policies
on piles `[5,6,7,9,10,11,13,14,15]` with seeds `[0,1,2]`: 54 games.
Testing pairs the same opponent seeds on piles
`[17,18,19,21,22,23,25,26,27]`: 27 pairs.

Each held-out episode starts with a clone of the fitted advisor. Test outcomes
never enter training, and one test episode cannot train another. Outcome
attribution uses exact strategy/version/context identities through
`StrategyOutcomeFeedback`, including idempotent replay checks.

The observed frozen-policy baseline won **9/27**, versus **27/27** after feedback:
an improvement of **66.7 percentage points**. This establishes strategy selection
on this synthetic distribution. It does not establish learned game rules,
unseen opponents, or general game-playing intelligence.

## Live evidence and correction

Live calls are optional and outside CI. They test eight proposal-only inputs
across the four domains, with a separate exact-output oracle. Models receive
public state and rules, never the expected-answer field. No external tools or
real-world effects are enabled.

The initial 2026-10-20 observations were:

| Provider/model | Result |
| --- | --- |
| Pokee `pokee-isaac` | 6/8 accepted; both game decisions failed |
| Gemini `gemini-3.5-flash-lite` | 5/8 accepted; required-field omissions and a wrong game move |
| OpenAI `gpt-4.1-mini-2025-04-14` | HTTP 401; proposal quality untested |
| Gemini `gemini-2.5-flash` | HTTP 404; superseded by the current documented model |

The original failures remain evidence. Prompt version 2 makes the two mandatory
fields explicit; it does not change any expected answer. Later runs retain
their prompt version, model, provider request identifier, elapsed time and token
usage when supplied. Missing cost is `null`, not zero. Provider availability and
proposal correctness are separate from action execution.

The second run accepted **7/8** proposals for each of Pokee and Gemini. Both
selected one stone from six, which loses against optimal play; the expected
move remains two. Explicit field instructions removed the observed format
omissions without resolving the strategic error. OpenAI remains unqualified
pending a working credential.

`RoutingCorrectionTest` reproduces the observed Pokee mistakes offline. It
admits their independently evaluated outcomes and separately measures a local
candidate. Future routing then chooses that candidate on four unseen positions
`[7,9,10,11]`, all scored by an exhaustive state solver rather than the
candidate's modulo rule. There are no additional provider calls. This proves
that evidence can improve the assembled system even when a model is unreliable;
it does not turn a failed provider observation into a successful one.
The test also replays the partly correct second-run evidence and requires the
same four unseen positions to succeed.

## Confidence compatibility

Orchestration now treats omitted and explicit null confidence as unknown,
including implicit controllers and nested agents. Any unknown contributor
makes the aggregate unknown. Inspect `worker_results` for individual measured
scores; the aggregate does not estimate missing evidence. Explicit numeric
ratios in `[0,1]` retain their meaning and known low scores still trigger the
hierarchical fallback. Numeric strings, booleans, nonfinite and out-of-range
scores fail validation. Hosts previously relying on the implicit `1.0` must
supply a measured score or handle null. Completion, confidence, authorization
and execution receipts remain distinct.

## Prerequisite compatibility

Sequential orchestration now stops at the first worker whose status is not
`completed`. Its receipt remains in `worker_results` and output context, the
aggregate retains that status, and metadata identifies `stopped_worker` and
`skipped_workers`. Unexecuted workers are not represented as completed results.
Successful chains retain ordering and accumulated context.

Reflexive orchestration stops when its producer or verifier is non-completed,
without implicitly retrying uncertain work. Only an actually completed
verifier returning Boolean `true` in `output.passed` certifies a proposal.
False or malformed acceptance can supply feedback for a bounded revision but
cannot report success. Nested agents preserve the child's status so a parent
sequential chain cannot bypass these rules. This changes earlier permissive
behavior; applications needing reconciliation must perform it explicitly.
These changes concern sequential/reflexive orchestration, not a new durable
scheduler or an authorization policy.

See [test configuration](../tests.md) for opt-in commands. Transport contracts
follow the [OpenAI Chat Completions reference](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create),
[Gemini content API](https://ai.google.dev/api/generate-content) and
[Pokee API documentation](https://console.pokee.ai/docs).

## Adoption and release boundary

To compare another host, supply sanitized inputs, an expected state/receipt
ledger, current authorization, an exact package revision and the command that
reproduces the result. Compare ordered effects and state separately from prose
or ranking. A host should add wrong-tenant, revoked-grant and concurrent-writer
cases before claiming deployment readiness.

Waiting here uses cooperative Fibers and pending response receipts. Recomposition
means stopping an old graph and admitting a new immutable graph. Receiver
ledgers, model references and recovery are process-local fixtures. Durable
recovery, learned topology, arbitrary NPC personality, media perception and
production effectiveness remain unproven. A prerelease may publish these
experiments with those limits; it must not imply production qualification.
