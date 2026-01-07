# Simulation World Example

This example uses the generic `Simulation` engine to model a simple **time-stepped disaster-response world**.

## What it demonstrates

- Using `Simulation` and `ISimulatable` to advance a shared world state.
- Modeling basic dynamics:
  - `road_condition_index` (worsening then recovering)
  - `demand_level` (rising demand for supplies)
- Producing a deterministic JSON timeline suitable for later integration into other modules (routing, Markov, GA, etc.).

## Scenario

The world tracks:

- `road_condition_index`: proxy for overall network degradation.
- `demand_level`: aggregate demand at hospitals/shelters.

Entities:

- `RoadConditionEntity`:
  - Increases `road_condition_index` during the early flood phase.
  - Gradually recovers conditions after a certain number of ticks.
- `DemandEntity`:
  - Increases demand rapidly at first, then at a slower rate as systems stabilize.

## How to run

From the project root:

```bash
php examples/disaster_response/simulation_world/run.php --seed=123
```

## Outputs

JSON to stdout:

- `seed`: RNG seed used.
- `ticks`: number of simulation ticks.
- `timeline`: array of per-tick snapshots, each including:
  - `road_condition_index`
  - `demand_level`
  - `tick`

This example is domain-specific, but the `Simulation` and `ISimulatable` types remain general-purpose for any discrete-time process.
