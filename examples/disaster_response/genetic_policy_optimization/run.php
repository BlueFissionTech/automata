<?php

declare(strict_types=1);

use BlueFission\DevElation;
use BlueFission\Obj;
use BlueFission\Automata\Genetic\Population;
use BlueFission\Automata\Genetic\UniformCrossover;
use BlueFission\Automata\Genetic\RandomMutation;
use BlueFission\Automata\Genetic\FitnessFunction;

require __DIR__ . '/../../../vendor/autoload.php';

// Seeded randomness for determinism.
$seed = 123;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--seed=')) {
        $seed = (int)substr($arg, strlen('--seed='));
    }
}

mt_srand($seed);
DevElation::up();

/**
 * Simple chromosome encoding a dispatch policy:
 * - risk_weight: how much to penalize risky routes.
 * - time_weight: how much to penalize slow routes.
 * - capacity_bias: preference for higher-capacity assets.
 */
class DispatchPolicyChromosome extends Obj
{
}

/**
 * Fitness: lower total cost across a tiny synthetic disaster map
 * (multiple routes + assets) → higher fitness.
 */
class DispatchPolicyFitness extends FitnessFunction
{
    public function evaluate($individual): float
    {
        if (!$individual instanceof DispatchPolicyChromosome) {
            return 0.0;
        }

        $data = $individual->data();

        $riskWeight   = (float)($data['risk_weight'] ?? 1.0);
        $timeWeight   = (float)($data['time_weight'] ?? 1.0);
        $capacityBias = (float)($data['capacity_bias'] ?? 1.0);

        // Synthetic “routes” in a flood scenario.
        $routes = [
            // id           time  risk  capacity
            ['id' => 'road_main',   'time' => 30, 'risk' => 1, 'capacity' => 3],
            ['id' => 'back_road',   'time' => 45, 'risk' => 2, 'capacity' => 2],
            ['id' => 'boat_route',  'time' => 60, 'risk' => 3, 'capacity' => 4],
            ['id' => 'airlift',     'time' => 25, 'risk' => 4, 'capacity' => 1],
        ];

        $totalCost = 0.0;

        foreach ($routes as $route) {
            $time     = $route['time'];
            $risk     = $route['risk'];
            $capacity = $route['capacity'];

            $capacityScore = $capacityBias * $capacity;
            $cost = $riskWeight * $risk + $timeWeight * ($time / 60.0) - $capacityScore;

            $totalCost += $cost;
        }

        return 100.0 - $totalCost;
    }
}

// GA parameters.
$populationSize = 12;
$generations    = 20;
$mutationRate   = 0.2;
$crossoverRate  = 0.5;

$population = new Population();
$fitness    = new DispatchPolicyFitness();
$mutation   = new RandomMutation($mutationRate);
$crossover  = new UniformCrossover($crossoverRate);

// Initialize population with random policies.
$population->initialize($populationSize, function () {
    $individual = new DispatchPolicyChromosome();
    $individual->assign([
        'risk_weight'   => mt_rand(0, 100) / 50.0,
        'time_weight'   => mt_rand(0, 100) / 50.0,
        'capacity_bias' => mt_rand(0, 100) / 50.0,
    ]);

    return $individual;
});

$log = [
    'seed'        => $seed,
    'generations' => $generations,
    'history'     => [],
];

for ($gen = 0; $gen < $generations; $gen++) {
    $individuals = $population->getIndividuals();

    // Evaluate fitness for each individual.
    $scored = [];
    foreach ($individuals as $idx => $ind) {
        $scored[] = [
            'index'   => $idx,
            'fitness' => $fitness->evaluate($ind),
            'policy'  => $ind->data(),
        ];
    }

    usort($scored, function ($a, $b) {
        return $b['fitness'] <=> $a['fitness'];
    });

    $best = $scored[0];

    $log['history'][] = [
        'generation' => $gen,
        'best_fitness' => $best['fitness'],
        'best_policy'  => $best['policy'],
    ];

    // Selection: keep top half.
    $population = $population->selection(function (array $pool) use ($fitness) {
        usort($pool, function ($a, $b) use ($fitness) {
            return $fitness->evaluate($b) <=> $fitness->evaluate($a);
        });

        return array_slice($pool, 0, (int)ceil(count($pool) / 2));
    });

    // Refill population via crossover.
    $parents = $population->getIndividuals();
    $nextGen = $parents;

    while (count($nextGen) < $populationSize) {
        $p1 = $parents[array_rand($parents)];
        $p2 = $parents[array_rand($parents)];

        $offspring = $crossover->cross($p1, $p2);
        $nextGen[] = $offspring;
    }

    $population = new Population($nextGen);

    // Mutate in-place.
    $population->mutate(function ($individual) use ($mutation) {
        $mutation->mutate($individual);
    });
}

echo json_encode($log, JSON_PRETTY_PRINT) . PHP_EOL;

