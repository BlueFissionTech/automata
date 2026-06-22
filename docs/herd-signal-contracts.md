# HERD Signal Contracts

Automata defines HERD authentication contracts as provider-neutral arrays that
auth adapters can validate without giving Automata ownership of credentials,
identity proofing, or host-specific security flows.

## Shapes

`HerdSignalContract::signal()` normalizes one risk signal:

- `id`
- `type`
- `value`
- `score`
- `weight`
- `confidence`
- `sensitivity`
- `retention_days`
- `evidence`
- `timestamp`

`HerdSignalContract::context()` normalizes request context:

- `subject_ref`
- `session_ref`
- `action`
- `environment`
- `metadata`
- `privacy`

`HerdSignalContract::result()` returns:

- `score`
- `decision`
- `challenge`
- `restrict`
- `thresholds`
- `signals`
- `context`
- `reasons`

## Thresholds

HERD scores are normalized from `0.0` to `1.0`. Default decisions are:

- below `0.35`: `allow`
- `0.35` and above: `challenge`
- `0.65` and above: `restrict`
- `0.9` and above: `deny`

Adapters can override thresholds per auth surface, but should keep the same
decision names so contract fixtures stay portable.

## Privacy And Retention

Signals should avoid raw credentials, raw device secrets, and full raw network
payloads. Use references or hashes in `subject_ref`, `session_ref`, and
`evidence` when sensitive values must be correlated by the host runtime.

Default retention guidance:

- `public`: 90 days, max 365 days
- `internal`: 30 days, max 180 days
- `sensitive`: 7 days, max 30 days

The host application remains responsible for applying jurisdiction-specific
privacy rules and deleting any external evidence stores.

## Adapter Contract Tests

Downstream adapters can consume the contract by asserting:

- signals normalize score, confidence, sensitivity, and retention fields
- result scores map to `allow`, `challenge`, `restrict`, or `deny`
- sensitive evidence is referenced rather than embedded as raw secrets
- threshold overrides preserve the standard decision names
