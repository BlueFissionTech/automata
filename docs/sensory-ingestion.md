# Sensory input in Cortex

`Input` belongs at the application ingestion boundary. It runs ordered synchronous
processors and emits normalized content through `Event::COMPLETE`. `Sense` belongs
after normalization and before experience projection: it supplies descriptive
chunk statistics and an attention heuristic. Neither class supplies semantic
interpretation, source authorization, outcome labels or model activation.

The separate example is `php examples/generic/cortex/sensory.php`. Its
[assembly](../examples/generic/cortex/SensoryCapture.php) is example-owned; it adds
no new library interface. It uses this flow:

```text
host-selected text and source/trace identity
  -> Input: validate, lowercase, collapse whitespace
  -> Sense: custom word preparation, first-sweep statistics and events
  -> explicit Statement + Context projection
  -> immutable Experience, retaining raw text and its digest
  -> separately supplied fixture Outcome labels
  -> ExperienceRecomposer -> isolated classifier -> held-out predictions
```

## What each stage contributes

| Stage | Demonstrated behavior | Boundary |
| --- | --- | --- |
| Input | Normalize bounded text without dropping negation or literal `"0"` | The host chooses admissible sources; normalization does not authenticate them |
| Sense | Real sweep counts, completion events, weighted chunks, variance and attention | CRC32 grouping can collide; measurements are not meaning, confidence or a hard compute budget |
| Adapter | Preserve text, source, trace and sensory measurements; construct a `reported` statement | The relation is declared by the adapter, not parsed by Sense |
| Inspection hint | Fewer than two distinct chunks suggests inspection; varied text gets a standard hint | A fixture policy for optional review, never permission or automatic training eligibility |
| Learning | Only labelled observations become training examples; 12 examples train real Naive Bayes and 6 held-out texts are evaluated | Small synthetic regression evidence; no model activation or production quality claim |

## Deliberate compatibility choices

- Each observation creates a fresh Input/Sense pair, so persistent processors and
  recursive sweep state cannot carry over from another observation.
- `Sense::setPreparation()` supplies fixed word boundaries for every sweep. This
  domain policy preserves words such as `not` that the default language preparer
  treats as noise. Default preparation now returns its tokens, including `"0"`.
- Consume the public return from `invoke()`: it retains the original sweep before
  optimization. The final outer `COMPLETE` describes the same observation; nested
  events may still repeat during enhancement. The example verifies the return
  against first `SUCCESS` and records event counts instead of treating each event
  as another observation. Raw and normalized text remain separate from grouped chunks.
- Admit 1–32 words and at most 256 raw bytes of ASCII text. Reject unsupported,
  empty and oversized content before analysis; do not truncate or coerce it.
  These are fixture input bounds, not execution deadlines. Unicode, media,
  streaming, concurrent capture and hostile in-process hooks are not qualified.
- Use direct Input events and Sense invocation. `InputArray` still needs queue
  runtime support, type routing and callback-contract work; this example does not
  qualify that coordinator or require Memcached.

Raw text retention is useful here for auditable synthetic evidence. Real hosts
must define consent, privacy, redaction and retention policy before retaining it.
Source names and hashes are provenance fields, not authentication credentials.

## Observable proof

The command emits 22 boolean gates and exits nonzero on failure. It checks actual
normalization, retained chunks/events, bounded fixture recursion, source digest,
negation/zero, repetition-sensitive inspection, unknown confidence, isolated
observations, detached snapshots/round trips, rejected input, label-gated
projection, disjoint holdout and classifier predictions. Constant predictions and
deliberate loss of training content are negative controls.

Four additional gates exercise uncustomized Sense preparation, literal zero,
outer completion/return parity and independent repeated invocations.

Focused regression tests:

```bash
php vendor/bin/phpunit --do-not-cache-result tests/Automata/Sensory
php examples/generic/cortex/sensory.php
```

## Updated library contracts

Each public `Sense::invoke()` resets prior observation state. `reset()` also clears
the captured input, matrix and buffer; listeners' previously delivered snapshots
are unaffected. Public invoke/reset/setter reentry during a sweep throws
`LogicException`; internal enhancement remains supported. Exceptions release the
guard so a corrected next observation can run. Custom preparation and build hooks
must supply arrays of strings. Invalid sampling/configuration fails before success.

Novelty looks up the translated key actually used for grouping, so repeated chunks
do not repeatedly earn novelty attention. Sampling derives valid row/column
coordinates from a flat position. Attention remains a heuristic; hosts still own
strict time, memory and execution limits.

`Input::name()` setters, `setProcessor()` and `scan()`, plus Sense preparation/parent
setters and `reset()`, return their instance for chaining. Input results still
arrive in COMPLETE context. Only null selects the constructor's identity processor;
invalid callbacks now fail at registration. Optional scan processors remain
registered for future scans. Literal source name `"0"` is now settable.

These are behavioral compatibility changes: callers that relied on null mutator
returns, implicit coercion, retained sweep depth or the deepest/pruned result should
adapt before upgrading. The example proves bounded integration, not general sensory
maturity. Queue contracts and modality-specific adapters remain unqualified.
