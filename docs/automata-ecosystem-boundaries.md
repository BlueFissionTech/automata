# Automata Ecosystem Boundaries

Automata is an upstream capability library for intelligence, agents, memory,
language, strategy, telemetry, governance, simulation, orchestration, and
related utilities. Dependent projects can reveal useful needs, but shared
Automata contracts should be described as general reusable capabilities rather
than package-specific features.

## Capability Ownership

- Treat Automata as the owner of reusable AI and automation primitives.
- Avoid one-off or consumer-coupled APIs.
- Prefer signatures that fit Automata's existing code style and can be adopted
  by any consumer with the same class of need.
- Keep new capabilities concrete and idiomatic for the Blue Fission library
  family, while leaving extension points when existing patterns call for them.
- Prefer Blue Fission packages and established local patterns over third-party
  packages where the codebase already depends on those surfaces.

## Dependency Boundaries

It is appropriate to reference upstream dependencies when they affect
implementation, limitations, or capacity. Dependency notes should explain the
contract that Automata relies on, the limitation encountered, and the acceptance
criteria for a future change.

When Automata depends on a Blue Fission package such as DevElation or Synematic,
describe the dependency as an implementation or contract constraint. Do not
turn downstream consumer needs into Automata-specific product framing.

## Public Issue And PR Hygiene

GitHub issues, PR descriptions, review comments, and public-facing docs should
be sanitary, professional, and durable. They should read like product or
library work rather than local coordination notes.

- Do not frame a feature around a named dependent package unless that package is
  the actual upstream dependency being changed.
- Do not reference local filesystem paths, local-only workflow tools, private
  discussion details, or artifact-only evidence in public GitHub content.
- Summarize evidence in collaborator-usable language.
- Create discrete GitHub issues for trackable needs.
- Keep issues scoped to Automata's ownership and acceptance criteria.
- Target PRs at the correct base branch and avoid stacking unrelated work.

## Coordination Expectations

Coordination should produce repo-owned follow-up when work becomes actionable:

- Share Automata capabilities, ownership boundaries, and reusable contracts in
  active discussion rooms when cross-repo alignment is useful.
- Translate repeated or valid dependent-project requests into general Automata
  capabilities.
- Communicate available generic features, contracts, test evidence, and
  migration expectations without making the feature package-specific.
- When Automata needs upstream support, create or request a trackable issue in
  the owning repo with repro context and acceptance criteria.

## Evidence Expectations

- Prefer tests with actual or representative data.
- Add or update examples when a feature is meant to be adopted by external
  adapters or applications.
- Keep public evidence concise and collaborator-usable.
- Use local scratch notes only for local process; promote durable shared
  decisions into tracked documentation or GitHub issues.
