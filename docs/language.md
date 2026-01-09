# Automata Language System

This document describes the intent and structure of the `src/Automata/Language` module and how it fits into Automata’s broader goals of interchangeable AI strategies, narrative memory, and code-like interpretation.

## Overview

The language subsystem is a configurable semantic engine that can:

- Tokenize and classify text using a configurable token dictionary.
- Parse those tokens with a grammar into a syntax tree.
- Interpret the tree into semantic `Statement` objects (subject / behavior / object / context).
- Walk or “run” those statements as:
  - A lightweight DSL / configuration language.
  - Narrative structures that can be stored in `Scene` / `Holoscene` and summarized by `Log`.

This is the same family of components used by the Synthetiq chatbot library: Synthetiq wires a configured `Grammar`, `Documenter`, and `Walker` into an `Interpreter`, and then uses that interpreter to validate and enrich chat responses.

## Key Components

- `Grammar`
  - Holds `rules` (nonterminal expansions), `commands` (what each token type expects next), and a token dictionary.
  - `tokenize(string $input)` produces an array of `['match', 'classifications', 'expects']` token entries.
  - `parse(array $tokens)` turns tokens into a nested syntax tree rooted at `T_DOCUMENT`.

- `Token` / `Term`
  - Provide structured representations for lexical units and their grammatical roles.

- `StemmerLemmatizer`
  - Normalizes words so that grammar and tokens can match across inflections.

- `Statement`
  - A semantic container with fields like `subject`, `behavior`, `object`, `indirect_object`, `relationship`, `modality`, and `context`.
  - `percentSatisfied()` expresses how “complete” a statement is.
  - `entities()` returns a merged collection of subject / object / indirect object.

- `Documenter`
  - Accepts token dictionaries via `addRule($types, callable $fn, int $priority = 0)`.
  - `push($cmd)` feeds tokens and lets rules populate the current `Statement`.
  - Automatically starts a new statement once the current one is mostly satisfied.
  - Maintains an internal tree (`processStatements()` + `getTree()`) of completed statements.
  - `get_entity_type()` exposes the current entity role label (subject / object / indirect_object) for configuration layers that need it.

- `Walker` / `SyntaxTreeWalker`
  - `Walker` “runs” statements by traversing the Documenter tree, collecting a semantic action log per statement (type / subject / behavior / object / etc.). It is intentionally generic; consumers can use the log to drive DSLs or simulators without hard-wiring domain behavior into the class.
  - `SyntaxTreeWalker` traverses raw parse trees to pull out simple roles for command-style utterances.

- `Interpreter` (implements `IInterpreter`)
  - Composes `Grammar`, `Documenter`, and `Walker`:
    - `run($code)` tokenizes, pushes through the documenter, processes the statement tree, then asks the walker to traverse/process it.
  - Also exposes `isValid()`, `tokenize()`, and `parse()` helpers; this is what Synthetiq expects.

- `Reader` (new helper)
  - A non-breaking façade that adds narrative-oriented workflows:
    - `readDocument(string $text): array` – uses `Grammar` to tokenize and `Documenter` to build a statement tree.
    - `readTokens(array $tokens): array` – same as above but for pre-tokenized input (useful for tests and simple examples).
    - `toHoloscene(array $statements, Holoscene $holo, IWorkingMemory $memory, string $episodeId)` – projects statements into `Frame`/`Scene` and stores them as a Holoscene episode.
    - `narrateHoloscene(Holoscene $holo, ?string $place = null): string` – produces a `Log` narrative summarizing the stored episodes.

## Narrative & Memory Integration

The language system is designed to work hand-in-hand with Comprehension and Memory:

- Statements → Frames / Scenes
  - Each `Statement` becomes a `Frame` whose `values` map contains keys like `subject`, `behavior`, `object`, etc.
  - `Scene` ingests frames, extracts entities, and writes them into working memory (`Abs2Memory`) as `Context` objects.

- Scenes → Holoscene → Log
  - `Holoscene` stores multiple scenes under episode keys (e.g., `episode_flood_day1`).
  - `Holoscene::review()` prepares an assessment; `Reader::narrateHoloscene()` walks scenes and frames, deriving `Log` facts such as “Hospital A requests oxygen.”

This gives Automata a path from raw language to episodic, traversable memory and back to readable narrative summaries.

## Usage Patterns

1. **Configured chatbot (Synthetiq-style)**
   - Build `Grammar` + `Documenter` + `Walker` from configuration.
   - Pass them into `Interpreter` and then into Synthetiq as an `IInterpreter`.
   - Use `Interpreter::run()` for validation and structured command parsing during conversation.

2. **Narrative reader for disaster response**
   - Use `Documenter` rules tailored to logistic phrases (hospital requests, road closures, shelter capacity messages).
   - Use `Reader::readTokens()` or `Reader::readDocument()` to produce `Statement[]` from reports.
   - Store those statements in a `Holoscene` via `toHoloscene()`.
   - Call `narrateHoloscene()` to generate human-readable situation summaries.

3. **DSL / configuration interpreter**
   - Configure grammar/tokens to recognize constructs like `TYPE`, `LIKE`, `MIGHT`, `DOES`.
   - Attach `Documenter` + `Walker` rules that manipulate an application runtime instead of just building narrative frames.

## TDD & Extension Notes

- Prefer tests that:
  - Construct small token streams by hand and assert on `Statement` fields (`subject`, `behavior`, `object`).
  - Verify `Reader::toHoloscene()` produces frames whose `extract()` output matches the statement roles.
  - Assert that `narrateHoloscene()` includes expected facts and entities in the `Log` output.

- When extending:
  - Add new behavior via helper classes (like `Reader`) rather than changing `Interpreter` or `Grammar` signatures.
  - Keep the configuration-driven feel: most behavior should be driven by grammar/tokens/documenter rules, not hard-coded conditionals.
