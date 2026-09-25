# Strategy usage and unknown cost

`StrategyUsage` distinguishes an explicitly unknown cost from a measured zero:

```php
new StrategyUsage(['cost' => null]); // unknown
new StrategyUsage(['cost' => 0.0]);  // measured or declared zero
```

For compatibility, omitting `cost` still produces `0.0`. An adapter must pass
`cost => null` when it cannot attest to a zero or measured amount. Adding usage
preserves an unknown cost, and a usage record with unknown cost cannot satisfy
a finite `max_cost` limit. The router therefore declines an invocation whose
estimate explicitly has unknown cost under a finite spend cap.

This is a narrow representation and pre-invocation safety rule. It does not
provide a hosted transport, reserve money, reconcile a dispatched attempt,
validate provider receipts, or authorize retries. In particular, a completed
or failed adapter response with unknown actual billing still needs a separate
reservation and reconciliation contract before hosted execution is safe.
