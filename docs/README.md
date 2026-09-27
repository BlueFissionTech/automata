# Automata documentation

Start with the [package README](../README.md) for installation and runnable examples.
The Cortex guides describe contracts on this development branch; check the target
release before depending on them. Review and demo success do not publish a release.

## Build an evidence-driven agent

| Need | Guide | Runnable proof |
| --- | --- | --- |
| Capture experience and project labelled evidence | [Cortex integration and delivery](cortex-delivery.md) | [Foundation](../examples/generic/cortex/run.php) |
| Normalize sensory input before experience capture | [Sensory placement and limits](sensory-ingestion.md) | [Input and Sense](../examples/generic/cortex/sensory.php) |
| Decide when to train an isolated candidate | [Continual learning](continual-learning.md) | [Training through future Agent responses](../examples/generic/cortex/learn.php) |
| Compare candidates, activate and roll back | [Model lifecycle](model-lifecycle.md) | [Evaluation](../examples/generic/cortex/evaluate.php), [activation](../examples/generic/cortex/promote.php) |
| Select strategies under policy and admitted feedback | [Strategy routing](strategy-routing.md) | [Adaptive routing](../examples/generic/cortex/adapt.php) |
| Compose conditional strategies and overlapping workers | [Strategy workflows](strategy-workflows.md) | [Graph execution](../examples/generic/cortex/workflow.php) |
| Route reviewed parser-backed scripts | [Script strategies](script-strategy.md) | [Executable cognition](../examples/generic/cortex/script.php) |
| Release responses and acknowledge execution | [Response composition](response-composition.md) | [Composition](../examples/generic/cortex/respond.php), [Agent workers](../examples/generic/cortex/agent.php) |

The [Cortex example guide](../examples/generic/cortex/README.md) lists commands,
expected checks and limits. The [host adoption checklist](cortex-delivery.md#host-adoption-checklist)
explains which responsibilities an application must supply.

## Library contracts

| Area | Documentation |
| --- | --- |
| Agent tools and authority | [Agent capabilities](agent-capabilities.md), [capability registry and autonomy](agent-capability-registry.md), [delegation](agent-delegation.md) |
| Agent state and context | [Working memory](memory-working-memory.md), [comprehension and intelligence](comprehension-intelligence.md), [persona orchestration](agent-persona-orchestration-contracts.md) |
| Language and evidence | [Language](language.md), [claim normalization](claim-normalization.md), [statement compatibility](statement-bundle-compatibility.md), [feedback review records](feedback-review-records.md) |
| Prediction and reasoning | [Markov and pattern prediction](MarkovAndPattern.md), [decision trees](DecisionTree.md), [graph routing](GraphRouting.md) |
| Media and generation | [Media ingestion](Media.md), [typed generation runs](typed-generation-runs.md) |
| Shared contracts | [Qualification primitives](qualification-primitives.md), [runtime capability vocabulary](runtime-capability-vocabulary.md), [DevElation prototypes](develation-prototypes.md) |

For implementation boundaries, read the [specification](../SPEC.md) and
[architecture](../ARCHITECTURE.md). Contributors can use the
[operator entrypoints](../TOOLS.md) and [ecosystem boundaries](automata-ecosystem-boundaries.md).
