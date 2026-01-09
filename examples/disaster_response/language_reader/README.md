# Language Reader (Narrative + Holoscene)

## What this demonstrates

- Using the Automata language `Documenter` to turn simple sentences into semantic `Statement` objects (subject / behavior / object).
- Projecting those statements into `Frame` and `Scene` instances backed by `Abs2Memory`, then storing them in a `Holoscene` episode.
- Generating a Markdown narrative `Log` from Holoscene episodes via the `Reader` helper.

This example stays domain-specific to disaster response (hospital/shelter logistics) while keeping the underlying classes generic.

## How to run

From the project root:

```bash
php examples/disaster_response/language_reader/run.php "Hospital A requests oxygen and fuel for flood victims."
```

If no sentence is provided, a default hospital request is used.

## Inputs and outputs

- **Input**
  - A single sentence describing an event (e.g., a hospital requesting supplies).
- **Internal processing**
  - A minimal tokenizer classifies each word as an entity or operator.
  - `Documenter` rules:
    - First `T_ENTITY` → `subject`.
    - `T_OPERATOR` → `behavior`.
    - Second `T_ENTITY` → `object`.
  - `Reader::readTokens()` converts tokens → `Statement[]`.
  - `Reader::toHoloscene()` maps statements → `Frame`/`Scene` and stores them under an episode key.
  - `Reader::narrateHoloscene()` builds a `Log` narrative from the scenes.
- **Output**
  - JSON summary of statements (subject/behavior/object).
  - A Markdown-like narrative describing what happened.

## What to look for

- The JSON summary should show the extracted subject, behavior, and object.
- The narrative should include a `##Scene` header and a fact line similar to:
  - `Hospital A requests oxygen.`

## Notes

- This example does not modify `Interpreter`, `Grammar`, or existing language behavior; it adds a `Reader` helper that can also be used by more complex configurations such as Synthetiq.
- The same pattern can be extended to feed richer grammars, multi-sentence documents, and more detailed Holoscene episodes.

