# BlueFission Automata

`bluefission/automata` is a PHP library for intelligence, agents, memory, language,
strategy routing and simulation. It combines symbolic reasoning and machine
learning with explicit governance, evidence and trace contracts.

The Cortex example assembles these capabilities into executable experiments:
record experience, project training data, approve isolated candidate training,
compare models, adapt routing, compose responses and approve model activation or rollback. See the
[Cortex guide](docs/cortex-delivery.md) and [runnable examples](examples/generic/cortex/README.md).
The Cortex additions on this branch are staged for review; these docs do not imply
that they are available in a published release.

## Features

`bluefission/automata` integrates a wide array of AI and data science techniques into a cohesive library:

- **Expert Systems**: Leverage rule-based logic for decision making, simulating the decision-making ability of human experts.
- **Game Theory**: Analyze competitive environments according to the choices of decision-makers for strategic planning and simulations.
- **Scenario Modeling**: Inspired by NetLogo, it supports agent-based models for simulating interactions and processes within complex systems.
- **Genetic Algorithms**: Implement evolutionary algorithms that mimic natural selection for solving optimization problems.
- **Monte Carlo Search**: Rank candidate actions under uncertainty using repeated seeded rollouts and per-action reward statistics.
- **Monte Carlo Tree Search (MCTS)**: Explore sequential decisions with UCT-style selection, simulation, and backpropagation.
- **Path & Graphs**: Manage, analyze, and manipulate structures represented graphically including networks of nodes and edges.
- **Anomaly Detection**: Score behavioral activity, fingerprints, and context to flag unusual or risky patterns.
- **Media Ingestion**: Normalize text, image, audio, video, document, and URL inputs into consistent pipelines.
- **Natural Language Processing (NLP)**: Tools for text parsing, analysis, and understanding, enabling the library to process and interpret human language.
- **Claim Normalization**: Normalize raw, wrapped, and adapter-parsed textual claims into inspectable Statement semantics while preserving typed predicates as inert data. See [Claim Normalization](docs/claim-normalization.md).
- **Bounded Language Prediction**: Lightweight Markov and trigram predictors support single-sentence updates and bounded bulk training for moderate local catalogs without requiring a hosted model.
- **Large Language Models (LLM)**: Facilitate prompting and generating responses using large pre-trained models, integrating with tools like GPT for advanced text generation.
- **Typed Generation Runs**: Describe provider-neutral generation requests, steps, artifacts, diagnostics, partial outcomes, policy, evidence, and adapter-owned execution. See [Typed Generation Runs](docs/typed-generation-runs.md).
- **Agent Capabilities**: Register deterministic tool contracts, descriptive capability definitions, exact scoped autonomy grants, lifecycle hooks, session memory, Holoscene comprehension, orchestration patterns, DevElation-backed agent state/goal decisions, interpreter-facing integration contracts, and persona orchestration contracts around LLM agent loops. See [Agent Capabilities](docs/agent-capabilities.md), [Capability Registry And Autonomy](docs/agent-capability-registry.md), and [Agent Persona Orchestration Contracts](docs/agent-persona-orchestration-contracts.md).
- **Adaptive, Deterministic-First Strategy Routing**: Select exact, side-effect-free deterministic, learned, or generative strategy adapters under autonomy, eligibility, budget, trace, and explicit escalation policy. Optional `Intelligence` advice learns contextual quality and efficiency without bypassing those gates. See [Strategy Routing](docs/strategy-routing.md).
- **Composed Strategy Workflows**: Run versioned graph proposals through the router with conditional dependencies, fan-in, bounded retries, fallback, output thresholds and host-scheduled overlapping workers. `CompositeStrategy` works through the existing strategy interface. See [Strategy Workflows](docs/strategy-workflows.md).
- **Experiential Learning**: Capture immutable experience and outcome snapshots, project attributable training batches, trigger separately approved candidate training, compare exact model versions on held-out evidence, and admit feedback into advisory routing. See [Continual Learning](docs/continual-learning.md) and [Cortex contracts](docs/cortex-delivery.md).
- **Governed Model Activation**: Evaluate candidates before explicit host approval, activate a process-local model reference, and retain revision-bound promotion and rollback receipts. See [Model Lifecycle](docs/model-lifecycle.md).
- **Progressive Responses**: Coordinate weighted fragments, required dependencies, delivery acknowledgements and cancellation, including synchronous Agent workers and TaskTrace integration. See [Response Composition](docs/response-composition.md).
- **LLM Lane Pressure Management**: Assess semantic, operational, and execution pressure in provider-neutral agent workflows, with deterministic recommendations and a read-only LLM tool wrapper.
- **Feature Engineering**: Provides robust tools for transforming raw data into features that better represent the underlying processes to predictive models.
- **Data Science**: Basic machine learning functionalities alongside data manipulation, preparation, and visualization tools.
- **Input Management**: Sophisticated input type detection and handling, ensuring that data flows seamlessly through processing pipelines.
- **Modular Connectivity**: Connect module outputs to other module inputs, creating flexible and dynamic pipeline chains for complex data processing tasks.
- **Carrier-Backed Adapters**: Normalize runtime state over Develation `Arr`, `Obj`, and `IData` carriers without forcing unrelated modules into one implementation.
- **DevElation Primitives and Evaluators**: Use `Func` evaluators and readable fluent collection, string, numeric and supported object transformations, with strict validation before value construction.

## Using GOFAI and Modern ML Techniques

`bluefission/automata` uniquely integrates both traditional AI methods and modern machine learning techniques to provide a comprehensive toolkit:
- **GOFAI Techniques**: The library utilizes symbolic AI methods for creating systems that reason with logic and predefined rules, suitable for scenarios where decision paths need to be transparent and based on human-like logic.
- **Modern Machine Learning**: Incorporates statistical learning techniques for pattern recognition, predictive modeling, and data-driven decision-making, allowing the system to adapt and learn from data.

## Getting Started

The library requires PHP 8.2 or newer. Install a published package into an application
with Composer, then load `vendor/autoload.php`:

```bash
composer require bluefission/automata
```

To run repository examples, check out the revision you intend to evaluate and
install its dependencies. Composer archives exclude examples and tests. The current
CI environment uses PHP 8.3 for the locked development toolchain.

```bash
git clone https://github.com/BlueFissionTech/automata.git
cd automata
# Select the reviewed branch or tag before installing dependencies.
composer install
php vendor/bin/phpunit --do-not-cache-result
```

The Cortex demos use synthetic fixtures and require no credentials or hosted model
calls. Run them from the repository root after installing dependencies:

```bash
php examples/generic/cortex/run.php
php examples/generic/cortex/evaluate.php
php examples/generic/cortex/adapt.php
php examples/generic/cortex/respond.php
php examples/generic/cortex/agent.php
php examples/generic/cortex/promote.php
php examples/generic/cortex/learn.php
php examples/generic/cortex/workflow.php
php examples/generic/cortex/sensory.php
```

Together they report 108 boolean conformance gates as JSON and exit nonzero on
failure. They cover real classifier predictions, routing changes, receipt-gated
responses, model activation/rollback and [sensory ingestion](docs/sensory-ingestion.md).
The small frozen corpus proves repeatable
behavior; it does not establish open-world accuracy. Stores, model ownership and
fixture receiver ledgers remain process-local. Durable recovery, concurrent writers
and learned route/goal integration remain open work. The workflow demo adds graph
execution with cooperative Fiber overlap. The learning demo connects
experience capture, training, activation and subsequent Agent responses in one process.

Monte Carlo examples:

```bash
php examples/monte_carlo_route_planning.php
php examples/monte_carlo_tree_search_dispatch.php
```

Language prediction example:

```bash
php examples/markov_logistics_language.php
```

## Documentation

Use the [documentation index](docs/README.md) to find API guides by task. For the
experience-to-response loop, begin with the
[host adoption checklist](docs/cortex-delivery.md#host-adoption-checklist), then
[candidate training](docs/continual-learning.md), [model activation](docs/model-lifecycle.md)
and [response delivery](docs/response-composition.md). Each guide describes the
host responsibilities and links to executable evidence.

## Contributing

Include focused tests and a runnable example for behavioral changes. Pull requests
should explain intent, acceptance criteria, exact validation commands and known
limits. See the [delivery and release gates](docs/cortex-delivery.md#release-gates-still-open)
for the Cortex work and [operator entrypoints](TOOLS.md) for repository workflows.

Shared contributor guidance for ecosystem boundaries, dependency notes, public
issue hygiene, coordination, and evidence expectations lives in
[Automata Ecosystem Boundaries](docs/automata-ecosystem-boundaries.md).

## License

The package declares the MIT license in [composer.json](composer.json).
