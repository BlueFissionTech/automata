# Typed Generation Runs

Automata exposes provider-neutral generation run contracts under
`BlueFission\Automata\LLM\Generation`. They describe generation work and its
outcome without selecting a model provider, transport, queue, storage system, or
deployment mechanism.

## Contract Surface

- `GenerationRunRequest` carries stable run, task, correlation, causation, and
  trace identifiers together with input, profile hints, constraints, policy,
  evidence, context, cancellation state, and deadline.
- `GenerationStep` records one observable step, its status, usage, evidence,
  produced artifact identifiers, diagnostics, and timing.
- `GeneratedArtifact` represents inline or externally referenced output with a
  media type, optional hash and size, evidence, and metadata.
- `GenerationDiagnostic` provides stable severity, code, retry guidance,
  details, and evidence without requiring exceptions for expected failures.
- `GenerationRunResult` preserves completed, partial, failed, cancelled, and
  timed-out outcomes. Partial results retain usable artifacts and continuation
  state.
- `IGenerationRunner` is the adapter boundary for hosts that perform generation.
  Automata does not provide an implicit provider or execution implementation.

## Authority Boundary

These contracts are descriptive. Creating a request does not authorize model,
tool, filesystem, network, repository, publishing, or deployment actions. Host
applications remain responsible for policy evaluation, approval, provider
selection, execution, storage, retries, cancellation, and side effects.

Store policy and evidence in both the request and result when a run crosses an
asynchronous boundary. Use `TaskTrace` separately for detailed span accounting;
the run contract only carries the trace identifier and summary usage needed for
portable interchange.

## Example

```php
use BlueFission\Automata\LLM\Generation\GeneratedArtifact;
use BlueFission\Automata\LLM\Generation\GenerationRunRequest;
use BlueFission\Automata\LLM\Generation\GenerationRunResult;

$request = new GenerationRunRequest([
    'run_id' => 'release-notes-42',
    'input' => ['prompt' => 'Draft release notes from approved changes.'],
    'policy' => ['publish' => false, 'human_review' => true],
]);

$result = GenerationRunResult::completed($request, [
    new GeneratedArtifact([
        'artifact_id' => 'draft-42',
        'kind' => 'document',
        'media_type' => 'text/markdown',
        'content' => '# Draft release notes',
    ]),
]);
```

Run the complete example with:

```bash
php examples/generic/typed_generation_run.php
```
