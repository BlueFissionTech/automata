# Orchestration completion evidence

An aggregate `completed` result must mean every required worker completed.
Dispatch, available output, confidence, and execution status remain separate.
Raw worker statuses and receipts remain in `worker_results`.

Fan-out, hierarchical and Piano use the following group contract:

| Worker outcomes | Aggregate |
| --- | --- |
| Empty group or every worker completed | `completed` |
| Any uncertain, unrecognized or malformed explicit status | `uncertain` |
| Any partial result, or completed mixed with incomplete work | `partial` |
| Only pending, accepted or in-progress work | `in_progress` |
| Active mixed with failed, rejected, cancelled or timed-out work | `partial` |
| Only failed, rejected, cancelled or timed-out work | `failed` |

Unknown outcomes take precedence over partial success because the host still
needs reconciliation. Raw status values are retained rather than coerced into
success. Missing status on a legacy unwrapped worker result still defaults to
completed; an explicit unknown or null status does not.

Independent peers continue even when another peer returns an incomplete
result. This does not retry or undo any effect. A successful fallback cannot
erase a previous uncertain receipt.

Hierarchical and Piano controllers must return `completed` or `accepted`
before children can run. Once dispatch is admitted, aggregate status describes
the children, including fallbacks; controller evidence is retained separately.
Without children, the controller supplies the aggregate evidence. An implicit
controller supplies a completed planning decision with unknown confidence.

Sequential orchestration deliberately differs: it stops on the first
non-completed prerequisite and preserves that status. Reflexive orchestration
stops non-completed producer/verifier work, and only a completed verifier's
Boolean `true` certifies success. These differences reflect dependencies and
verification rather than independent peer scheduling.

Run `php vendor/phpunit/phpunit/phpunit --do-not-cache-result
tests/Automata/LLM/OrchestrationAggregateStatusTest.php` and
`php examples/generic/cortex/status.php`. The matrix covers worker order,
receipts, unknown confidence, controller gates and nested prerequisites.

The alpha.7 prerelease predates this correction. Its fan-out and Piano
aggregates may report completion for incomplete workers, and its hierarchical
aggregate may hide unknown status. Inspect worker evidence when evaluating
that version; issue [#164](https://github.com/BlueFissionTech/automata/issues/164)
tracks the correction.
