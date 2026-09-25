# Engine classification and timing

`Engine::classify($input)` tries registered processors in order. A processor may
provide `process($input)` followed by `guess()`, or a `predict($input)` method.
`null` means that processor supplied no result, so Engine continues to the next
one. Any other value, including `false`, `0`, and an empty string, is a result and
stops selection. The original input is returned only if every processor supplies
no result. Existing input/output filters and classification hooks still apply.

`Engine::time()` reports the most recent strategy attempt's elapsed monotonic
wall time in **seconds**. `stats()['avgtime']` uses the same unit and retains the
existing rolling-average calculation. These measurements include synchronous
strategy work; they are not CPU time, a timeout, or a resource-spend limit.

Run the provider-free example from the repository root:

```sh
php examples/generic/engine_classification.php
```

It shows null continuation, preservation of false and zero, fallback after no
result, and a nonnegative elapsed-seconds measurement. The fixture does not
establish predictor quality or authorize effects.
