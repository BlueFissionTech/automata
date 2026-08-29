# Qualification automation primitives

Automata provides deterministic, domain-neutral contracts for advisory qualification decisions. They can support lead qualification, intake prioritization, eligibility review, or other scored workflows without owning a CRM schema, delivery transport, or platform router.

## Existing capability inventory

- `GoalDecision` already represents bounded, ranked action options.
- `Assessment`, `Classification\Result`, and `Condition` provide evaluation and criterion concepts.
- `ReviewRecord` preserves human corrections, evidence, confidence, and policy strategy.
- Agent traces, governance, lane pressure, and orchestration provide execution-time controls when a host chooses to act on an advisory result.

The `Qualification` namespace composes these patterns into a narrower reusable workflow contract rather than replacing them.

## Contracts

- `QualificationCriterion` identifies a weighted signal and any required minimum.
- `QualificationScorer` deterministically returns a normalized `QualificationScore` with confidence, reasons, unmet criteria, evidence, and trace data.
- `NurtureSuggestion` is an advisory action with explicit allowed and prohibited conditions. Suggestions require review by default.
- `FollowUpPlan` holds a bounded number of semantic steps, plan and step expiry, stop conditions, and review metadata.
- `QualificationAudit` preserves evaluator, rule version, timestamps, evidence, review data, and correlation lineage.
- `QualificationResult` is the transport-neutral envelope for the score, safe suggestions, bounded plan, and audit record.

Scores remain advisory. A missing or below-minimum required criterion routes the result to `review`; it does not authorize a platform action. Hosts own consent policy, routing, delivery channels, CRM payloads, persistence, and side effects.
