# Claim Normalization

Automata provides provider-neutral claim normalization contracts under
`BlueFission\Automata\Language\Claims`. They let parsers and processors converge
raw documents, versioned wrappers, and textual claims on `Statement` semantics
without treating predicates as executable host expressions.

## Boundaries

- `ClaimEnvelope` preserves the source form, original payload, source context,
  policy, correlation, causation, trace, and metadata.
- `ClaimNormalizer` deterministically handles raw and wrapped payloads. Text
  parsing is injected by the host because language grammar belongs to the parser
  adapter rather than this interchange layer.
- `ClaimPredicate` stores comparison and logical operator trees as inert data.
  Predicates are never assigned to `Statement::condition()` and this normalizer
  does not evaluate them.
- `ClaimNormalizationResult` returns the normalized `Statement`, optional typed
  predicate, evidence, and stable diagnostics.
- Statuses distinguish normalized, unsupported, ambiguous, malformed, and
  policy-rejected inputs.

Processor, query planner, storage, and mutation behavior remain adapter-owned.
A consumer must explicitly translate a `ClaimPredicate` into its own safe query
or policy representation after validation. No host-language expression should
be accepted as an operator.

## Text Parser Adapter

Text parsers receive the source text, source context, and envelope. They return
an associative semantic payload, a `statement` wrapper, or a single `candidates`
entry. Multiple candidates are reported as ambiguous instead of being selected
implicitly.

```php
$normalizer = new ClaimNormalizer(
    fn (string $text, array $context): array => [
        'statement' => [
            'subject' => ['name' => 'incident'],
            'behavior' => 'requires',
            'object' => ['name' => 'review'],
        ],
        'evidence' => ['grammar' => 'application-v1'],
    ]
);
```

Run the complete example with:

```bash
php examples/generic/claim_normalization.php
```
