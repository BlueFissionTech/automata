# Capstone Multi-Strategy Dashboard (CLI)

This example demonstrates a small **integrated simulation** that combines:

- Genetic algorithms (policy evolution)
- Game theory (payoff matrix for joint actions)
- Discrete-time simulation (world dynamics)

within the **disaster-response logistics** domain.

## What it demonstrates

- Evolving a dispatch policy using a genetic algorithm:
  - `risk_weight`, `time_weight`, `capacity_bias`.
- Using a `PayoffMatrix` to evaluate joint actions between:
  - Logistics command (routes assets)
  - Hospital network (requests urgency)
- Stepping a shared world state with:
  - Road condition evolution
  - Rising demand for supplies
  - Per-tick dispatch decisions and utilities

The core library remains generic; only this example is domain-specific.

## How to run

From the project root:

```bash
php examples/disaster_response/capstone_multi_strategy_dashboard/run.php --seed=123 --steps=20
```

- `--seed=INT` controls RNG for both GA and simulation.
- `--steps=INT` controls the number of simulation ticks.

## Outputs

JSON to stdout with:

- `seed` and `steps`
- `best_policy`:
  - `fitness`
  - `policy` (`risk_weight`, `time_weight`, `capacity_bias`)
- `timeline`:
  - Per-tick snapshots including:
    - `road_condition_index`
    - `demand_level`
    - `metrics` (cumulative utilities)
    - `decisions` (per-tick joint actions and payoffs)
- `summary`:
  - `total_logistics_utility`
  - `total_hospital_utility`
  - `final_road_condition`
  - `final_demand_level`
  - `decisions_logged` (count in final state)

This capstone is intentionally CLI-first and structured for later extension into a richer dashboard or service.

