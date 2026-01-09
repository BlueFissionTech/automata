# Game Theory Allocation Example

This example demonstrates a simple **two-player normal-form game** in the disaster-response logistics domain, using the generic `PayoffMatrix` helper.

## What it demonstrates

- Encoding a payoff matrix for joint actions in a two-player game.
- Evaluating all pure strategy profiles and logging structured results.
- Keeping the Game Theory classes generic while the scenario is domain-specific.

## Scenario

Two players:

- **Logistics command** (Player 0)
- **Hospital network** (Player 1)

Each chooses between:

- `Conservative` – safer, slower routes
- `Aggressive` – faster, riskier routes

The payoff matrix encodes how each joint choice affects both parties in terms of net utility (balancing delay and risk).

## How to run

From the project root:

```bash
php examples/disaster_response/game_theory_allocation/run.php --seed=123
```

The `--seed` argument is accepted for consistency with other examples, but this script is currently deterministic.

## Outputs

The script prints JSON to stdout with:

- `seed`: the seed used for the run.
- `profiles`: an array of entries:
  - `actions`:
    - `logistics`: chosen action
    - `hospital`: chosen action
  - `payoff`:
    - `logistics`: scalar payoff
    - `hospital`: scalar payoff

This structure is suitable for:

- Quick inspection from the CLI.
- Future integration into a dashboard or capstone simulator.

