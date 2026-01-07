# Genetic Policy Optimization Example

This example shows how to use Automata’s genetic components to evolve a simple **dispatch policy** in the disaster-response logistics domain.

## What it demonstrates

- Using `Population`, `UniformCrossover`, `RandomMutation`, and `FitnessFunction` together.
- Encoding a dispatch policy as a small DevElation `Obj` (`DispatchPolicyChromosome`).
- Evolving weights that trade off route **risk**, **time**, and **capacity** across a synthetic flooded-road network.

## Why the model fits

Disaster response often involves:

- Choosing routes and assets under uncertainty.
- Balancing time-to-arrival, safety, and throughput.

A genetic algorithm is a natural fit for exploring policy spaces where gradients are unclear but candidate policies are easy to simulate and score.

## How to run

From the project root:

```bash
php examples/disaster_response/genetic_policy_optimization/run.php --seed=123
```

## Inputs

- `--seed=INT` (optional): sets the RNG seed for reproducible runs.
- A small synthetic “world” baked into the script:
  - Several routes with different time, risk, and capacity attributes.

## Outputs

- JSON log to stdout containing:
  - `seed`: seed used for the run.
  - `generations`: number of GA generations executed.
  - `history`: array of entries, one per generation:
    - `generation`: generation index.
    - `best_fitness`: best fitness value found that generation.
    - `best_policy`: the corresponding policy weights (`risk_weight`, `time_weight`, `capacity_bias`).

You can use this output as a deterministic benchmark for future changes, or as a starting point for more sophisticated policy evaluation.  
The library itself remains general-purpose; only the example is tied to the disaster-response domain.

