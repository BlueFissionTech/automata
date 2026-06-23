# Statement Bundle Compatibility

Automata can consume resolved language or query outputs without owning raw
parser internals. Adapters should emit a normalized semantic bundle after their
own parsing and resolution steps have completed.

## Statement Fields

Map resolved semantic output to `Statement` fields:

- `subject`: actor, entity, resource, or source object.
- `behavior`: action, relation verb, or requested operation.
- `object`: target entity, value, resource, or result object.
- `relationship`: relation label between subject and object.
- `indirect_object`: secondary target when one is present.
- `condition`: deterministic precondition or guard expression.
- `position`: location, ordering, or coordinate-like metadata.
- `context`: scope, confidence, phase, provenance, and status metadata.

The core Statement satisfaction path remains `subject`, `behavior`, and
`object`. Adapter-specific values such as confidence, phase, scope, provenance,
and status should use `Context` unless they become reusable Automata semantics.

## Adapter Metadata

Recommended `context` keys for resolved bundles:

- `confidence`: bounded confidence score from the resolving adapter.
- `phase`: resolution phase such as `parsed`, `resolved`, or `validated`.
- `scope`: adapter-owned scope label such as `query.result`.
- `provenance`: source, parser, grammar, or fixture references.
- `status`: `resolved`, `partial`, `ambiguous`, or `failed`.

This keeps query-language and grammar registries adapter-owned while still
allowing Automata reasoning, memory, comprehension, and goal code to consume a
stable bundle shape.

## Grammar Registry Boundary

Automata should expose grammar and language surfaces as stable normalized
contracts, not as a registry of downstream parser internals. A parser adapter
may maintain its own grammar registry, but the handoff into Automata should be
a `Statement`, `Context`, `Entity`, `Scene`, or Holoscene-compatible snapshot.

## Reasoning Entry Point

Resolved semantic outputs should enter Automata reasoning after semantic
resolution:

1. Adapter parses and resolves the source language.
2. Adapter emits normalized Statement fields plus Context metadata.
3. Automata can read the Statement snapshot, route it into comprehension,
   attach it to working memory, or evaluate goals and policies.
4. Host applications keep execution, persistence, and presentation decisions.

Do not expose raw parser ASTs, grammar-private node ids, or host execution
choreography as Automata Statement fields.
