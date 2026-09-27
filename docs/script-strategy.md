# Governed script strategies

`ScriptStrategy` adapts the existing DevElation parser and renderable contracts to
`IStrategy`. The host supplies a reviewed source string, stable identity/version,
fresh parser factory and current authorization callback. Grammar, parser registry
setup, tools and model bindings remain host responsibilities. No new language or
dependency is introduced.

Each run snapshots its input, authorizes execution before preparing the parser,
and returns a normalized result. `ScriptExecution` implements the existing
`IGenerator` interface around an optional host generator. Every slot receives a
fresh exact-operation/actor authorization check and consumes a bounded dispatch
allowance. `finish()` provides explicit early exit; `cancel()` stops optional work
without erasing completed generation receipts. Failures and denials latch even if
host preparation catches exceptions. Execution handles close when the run ends.

Acceptance is demonstrated by a separate Cortex script example and regression
tests: actual parser interpolation, fresh runs, early exit before generation,
denied callbacks, uncertain failures, cancellation, generation limits, routing,
TaskTrace and preserved unknown metrics.

This is trusted in-process execution, not a sandbox. Hosts must review source,
bindings, extensions and global registries and must not concurrently mutate parser
registries. Generation counts bound calls to the supplied generator, not retries
inside it, tokens, money, memory or wall time. Durable recovery, provider control
qualification and automatic retries are not supplied by this adapter.

## Host integration

```php
use BlueFission\Automata\Strategy\ScriptStrategy;
use BlueFission\Parsing\Parser;

// Configure reviewed parser registries before constructing/running strategies.
// $authorize returns a current AutonomyDecision for the supplied exact request.
$strategy = new ScriptStrategy(
    'intake', '1', 'Hello {$name}',
    static function ($source, $input, $execution): Parser {
        if ($input['closed'] ?? false) { $execution->finish('Request closed.'); }
        $parser = new Parser($source);
        $parser->setVariables(['name' => $input['name']]);
        return $parser;
    },
    $authorize,
    'actor-id',
);
$text = $strategy->predict(['name' => 'Ada']);
$receipt = $strategy->lastResult()->toArray();
```

The factory receives `(source, detachedInput, ScriptExecution)` and returns a fresh
`Parser` or `IRenderableElement`. Reusing a live parser object is rejected to avoid
retained variables and executed elements. Version the source **and** reviewed
factory/bindings together: the source SHA alone cannot attest arbitrary PHP code.

For controlled generation, supply an `IGenerator` to the constructor and call
`$execution->generate($element)` at the required preparation/element boundary.
This handle itself implements `IGenerator`, so a host can bind it to a compatible
per-run parser integration. Existing global generators, tools, preparers or direct
provider calls are **not** automatically intercepted. Never install a run handle
in a process-global registry shared by overlapping executions. The example uses
explicit generation during preparation and passes its result as parser data.

Host authorization receives actor, source SHA, input fingerprint, fresh run id,
exact capability id/version, and operation. Capabilities are `<script-id>.execute`
and `<script-id>.generate`; a decision must match actor, capability and version.
Generation also includes ordinal, element tag and an attribute fingerprint.
The host must check current scope, expiry, revocation and spend policy. Returning
an old allowed decision does not perform those checks automatically.
Script-level decisions may bound `max_invocations`, reserving the script plus its
maximum generator dispatches at entry, or one dispatch for an individual slot.
Other decision limits are unsupported and deny execution; they are not discarded.

## Results and routing

`run()` returns `ScriptResult`; `predict()` throws unless the status is `completed`
or `early_exit`. Other states are `denied`, `cancelled`, and `uncertain`. Exceptions
after admission conservatively mean uncertain effects; only the exception class
is retained. No automatic replay is supplied. Calls to `run()` again are new
independent executions, not idempotent retries; hosts must reconcile prior effects
before deliberately resubmitting input.

Every receipt records identity, input fingerprint, generation dispatches and output
hashes, elapsed time and authorization outcomes. Cost/confidence remain null.
`finish()` unwinds the script, and checkpoints prevent further adapter work even
if a factory catches the signal. `cancel()` preserves in-flight generation receipts
but withholds final script output. It cannot interrupt arbitrary blocking PHP code.
TaskTrace is optional; observer failures retain the result and report
`telemetry_status=failed`. Hosts own redaction/persistence of final text in traces.

`ScriptRouteAdapter` requires an explicit `sideEffectFree: true` assertion before
the router can select it. Merely being deterministic is not proof of purity.
With a generator attached its mode is conservatively `generative`, even if a given
branch does not generate. Router authorization remains required in addition to
the script's fresh execution/slot checks. A non-successful script throws through
the adapter, stopping automatic router escalation after uncertain work.

The existing numeric `StrategyUsage` schema cannot express unknown monetary or
energy usage. Its zero defaults are placeholders here, **not measured free work**;
the attached script evidence retains null and `cost_known=false`. Accordingly,
the adapter refuses requests with cost, energy or latency ceilings. Invocation
limits reserve one script dispatch plus the maximum generator dispatch allowance;
provider-internal retries remain unknown and unbounded by that count.
The router passes effective request **and grant** ceilings to adapter eligibility,
estimation and execution. The caller's original request remains unchanged.
Explicit false/zero/empty input survives rebuilding. An explicitly empty
`allowed_modes` list now stays empty and denies every mode, instead of falling
back to the default deterministic mode through legacy value assignment.

## Runnable proof

```bash
php vendor/bin/phpunit --do-not-cache-result tests/Automata/Strategy/ScriptStrategyTest.php
php examples/generic/cortex/script.php
```

The [example](../examples/generic/cortex/script.php) emits 16 gates covering real
parser interpolation, literal zero, independent runs, deterministic branches,
hybrid output, exact slot authorization, retained receipts, unknown measurements,
early exit, closed handles, denial, router/Intelligence selection, unsupported
budgets and trace identity. Generation is a deterministic fixture, not a live
provider qualification. Tests additionally cover cancellation, uncertain effects,
caught failure/exit signals, parser reuse, reentry and observer failures.
