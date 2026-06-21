# AGENTS.behavior.md - Project-Specific Behaviour Rules

This file is reserved for **project-specific** behaviour and preferences that should override the base harness rules in `AGENTS.md` without being overwritten when the harness is refreshed.

Guidelines:

- Keep global, reusable rules in `AGENTS.md`.
- Put repo-specific policies here, for example:
  - Custom service preferences for this project.
  - Additional branching patterns or naming conventions.
  - Local-only tooling or scripts that shouldn't be propagated to other repos.
- When Codex or skills need to write or update project-specific rules, they should prefer editing `AGENTS.behavior.md` instead of `AGENTS.md`.

## Automata-specific development approach

- Favor a **tests + examples first** workflow (red → green → refactor) for new or changed behavior.
- For each major subsystem (collections, decision trees, expert, game theory, genetic, graph/memory/ABS, language, LLM):
  - Add or update **unit tests** under `tests/`.
  - Add or update a runnable **example** under `examples/` that demonstrates idiomatic (Develation-style) usage.
  - Only refactor implementations after tests and examples clearly document the intended behavior.

## Ecosystem role and dependency boundaries

- Treat Automata as an upstream capability library. Do not shape features, public contracts, docs, GitHub issues, or PR language around a named dependent package.
- Dependent consumers may reveal real needs, but Automata features should be described as general, reusable capabilities within Automata's natural sphere: intelligence, agents, memory, language, strategy, telemetry, governance, simulation, orchestration, and related utilities.
- Avoid one-off or consumer-coupled APIs. Prefer ubiquitous signatures that fit Automata's existing code style and can be adopted by any consumer with the same class of need.
- Avoid forced abstraction. Keep new capabilities as prescriptive and concrete as the rest of the Blue Fission libraries, while leaving clear extension points when the existing patterns call for them.
- It is appropriate to call out upstream dependencies when they affect implementation, limitations, or capacity. Be explicit about DevElation, Synematic, or other upstream package contracts when Automata depends on them.
- Prefer Blue Fission packages and established local patterns over third-party packages. Use DevElation primitives, data objects, configuration, storage, behavior, and prototype patterns where they fit the codebase.

## Public issue and PR hygiene

- GitHub issues, PR descriptions, review comments, and public-facing docs should be sanitary, professional, and durable. They should read like product/library work, not local coordination notes.
- Do not name dependent consumer packages in GitHub issues or PRs as the reason a feature exists. Frame the need as a general user story, reliability gap, integration boundary, or capability improvement.
- Do not reference local workflow tools, local filesystem paths, private discussion details, or artifact-only evidence in GitHub. Summarize evidence in collaborator-usable language.
- Create discrete GitHub issues for new trackable needs. Keep them scoped to Automata's ownership and acceptance criteria, with dependency notes only when an upstream package genuinely constrains the work.
- Target PRs at the correct branch. Do not stack unrelated PRs onto feature branches unless the dependency is explicit and intentional.

## Discussion and coordination behavior

- Accept discussion invitations liberally; they are intentional coordination surfaces.
- Use discussion rooms actively to share Automata capabilities, clarify ownership boundaries, ask focused questions, and explain best practices from the Blue Fission ecosystem.
- Do not make named dependent consumers feel like Automata is catering to them. Instead, translate repeated or valid requests into general Automata capabilities and explain the reusable contract.
- When dependent consumers need updates, communicate the available generic feature, contract, test evidence, and migration expectations without making the feature sound package-specific.
- When Automata needs upstream support, message or suggest changes to the owning upstream package with clear repro, acceptance criteria, and the implementation constraint.
- Acknowledge or mark read completed, stale, duplicate, or informational local coordination messages so they do not recur. Leave unresolved items visible only when they require real follow-up.

## Work execution and evidence

- After a coding task is complete, continue useful coordination when it improves shared understanding: room updates, issue comments, artifact logs, follow-up issues, and focused questions are appropriate.
- Stop when implementation turns into guessing. Leave a descriptive PR note or issue question rather than over-engineering past the evidence.
- Prefer real tests with actual or representative data. Ask collaborators for fixtures when needed, or generate bounded fixtures that preserve the contract being tested.
- Use artifact logs for local scratch context and process notes. Keep public issues and PRs polished.
- Coordinate Docker usage, services, and port availability through the approved harness path before running shared environment work.

If both files exist, rules in `AGENTS.behavior.md` should take precedence over conflicting rules in `AGENTS.md` for this repository.
