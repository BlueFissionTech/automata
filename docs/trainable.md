# Trainable methods (experimental)

`BlueFission\Automata\Behavioral\Trainable` is an opt-in trait for active
DevElation `Obj` components. It has no constructor or magic-method override,
so it can be composed with `Configurable`, `Programmable`, and `StateMachine`.
It does **not** intercept a normal PHP method call: register a public method
or callable operation, then invoke it through `trainableInvoke()`.

```php
use BlueFission\Automata\Behavioral\Trainable;
use BlueFission\Automata\Behavioral\TrainableMethod;
use BlueFission\Obj;

class ReadOnlyEstimator extends Obj
{
    use Trainable;

    #[TrainableMethod(minimumSamples: 3, allowPrediction: true, behaviors: ['OnEstimateReady'])]
    public function estimate(string $key): string { /* read-only work */ }
}

$estimator = new ReadOnlyEstimator();
$estimator->registerAttributedTrainableMethods();
$estimator->when('OnEstimateReady', function ($event, $meta): void {
    // $meta->info is 'method' or 'model'; $meta->data contains the output.
});
$result = $estimator->trainableInvoke('estimate', 'example');
```

Direct `estimate()` calls still behave normally and are not observed. A method
without the attribute can instead be registered with
`registerTrainableMethod('estimate', new TrainableMethod(...))`. For dynamic
`Programmable` tasks, use `registerTrainableOperation('task', $callable, ...)`;
the operation retains the host's normal behavior gates inside the callable.

## Observation and prediction

By default, the trait only observes. Each successful source invocation records
the arguments, output, explicitly tracked feature state, and a UTC invocation
timestamp in a bounded, per-instance `MemoryTrainingRegistry` backed by a
DevElation `Arr`. `registerTrainableState($name, $reader, $asFeature)` makes
selected state part of the exact-match key; `$asFeature = false` stores it as
descriptive metadata only. Time is metadata, never a predictive feature.

Only `null`, booleans, finite numbers, strings up to 4 KiB, and bounded nested
arrays are recorded. Objects, resources, oversized or deep values are executed
normally but not recorded. Do not register secrets, personal data, mutable
authorization state, or outputs whose storage is not approved. A registry
implementation controls durable retention, access, deletion, and encryption;
this trait does not silently provision or write external storage.

The default `ExactLookupStrategy` builds a one-to-one lookup from repeated
input and feature-context snapshots. It reports support count and the frequency
of the most common output. No match, too few total examples, too few matching
examples, or confidence below the configured minimum causes a source call.
`allowPrediction` is **false** unless explicitly enabled for a method whose
body can safely be skipped. Every `probeEvery`th eligible prediction executes
the source and updates evidence, limiting—but not eliminating—staleness. An
injected `TrainingStrategy` or `TrainingStrategyMatrix` can use a different
model; it must return a bounded output with meaningful per-prediction confidence
and support. A PHP-ML or external Python model belongs in such an adapter; no
shell runner, serialization format, credential, provider call, or persistence
backend is implied by the core trait.

`setTrainingRegistry()` accepts any `TrainingRegistry`. A Chronicler, database,
file, MemQ, or Redis adapter can implement that small port, with its own
configuration and approval policy. Shared registries should partition by host
class and operation, version schemas/models, and handle concurrent writes.

## Lifecycle and safety

Both source and model paths apply the same DevElation input/output filters and
`before`/`after` actions, and dispatch `OnTrainableInvoked` and
`OnTrainableReturned` Obj behaviors. The path also dispatches
`OnTrainableObserved` or `OnTrainablePredicted`. Each name in the method's
`behaviors` option is also dispatched once after
`OnTrainableReturned`, whether the source or model produced the return. Register
handlers on the Obj with `when()`. These are Obj behavior notifications via
`dispatch()`, not `StateMachine::perform()` transitions; the model path does
not replay state transitions or other effects hidden in the source method.
Avoid manually dispatching the same method-specific behavior inside a source
method, since that would emit it twice on a source call. For a class named `MyWorker`,
the per-operation hook prefix is
`automata.trainable.v1.myworker.<operation>`; phases are `.input`, `.before`,
`.output`, and `.after`. Input filters return an argument list. Output filters
run once on both paths; observations store the pre-filter source output so a
non-idempotent filter is not applied twice. Observation metadata carries
`schema_version: 1`. Hooks and behaviors may have
effects, so they must be reviewed with the host's policy.

Effects hidden **inside** the original method body cannot be replayed on a
model return. If such effects matter, keep `allowPrediction: false`, or move
the required effects into the shared Trainable lifecycle before opting in.
State-changing methods, billing, authorization decisions, writes, irreversible
actions, simple arithmetic, and cheap case transforms are poor bypass targets.
Read-only expensive computations and repeatable advisory API responses may be
better candidates, subject to freshness, privacy, and output-quality review.
Predictions are not a substitute for held-out validation or governance for
probabilistic models. The default exact lookup is deliberately conservative.

Run the provider-free example with `php examples/generic/trainable_method.php`.

## Potential library integrations

- Game theory: observe a pure payoff or candidate-ranking calculation with the
  complete game-state projection as context. Do not bypass `Player::decide()` as
  written: it adopts a decision and emits a hook.
- Simulation: use a read-only forecast or advisory next-state projection, not
  `ISimulatable::step()` or the simulation loop, which change world state.
- Intelligence and Engine: test pure strategy advice or a classification
  projection in isolation. Do not bypass routing governance, traces, model
  promotion, `Engine` lifecycle work, or external effects.
- Cortex-style demos: compare exact lookup with a candidate model on a
  provider-free, read-only stage, while keeping response delivery, receipts,
  authorization, and model promotion on their existing governed paths.

These are candidate seams, not automatic trait adoption or evidence of an
accuracy or latency gain. Each needs its own representative fixture, held-out
evaluation, state/freshness key, and effect-boundary review.
