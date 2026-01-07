<?php

declare(strict_types=1);

use BlueFission\DevElation;
use BlueFission\Obj;
use BlueFission\Automata\Simulation\Simulation;
use BlueFission\Automata\Simulation\ISimulatable;
use BlueFission\Automata\GameTheory\PayoffMatrix;
use BlueFission\Automata\Genetic\Population;
use BlueFission\Automata\Genetic\UniformCrossover;
use BlueFission\Automata\Genetic\RandomMutation;
use BlueFission\Automata\Genetic\FitnessFunction;

require __DIR__ . '/../../../vendor/autoload.php';

// CLI args: --seed, --steps
$seed = 123;
$steps = 20;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--seed=')) {
        $seed = (int)substr($arg, strlen('--seed='));
    } elseif (str_starts_with($arg, '--steps=')) {
        $steps = (int)substr($arg, strlen('--steps='));
    }
}

mt_srand($seed);
DevElation::up();

/**
 * GA chromosome for dispatch policy weights.
 */
class DispatchPolicyChromosome extends Obj
{
}

/**
 * Fitness over a synthetic flooded-road network.
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

        $routes = [
            ['time' => 30, 'risk' => 1, 'capacity' => 3],
            ['time' => 45, 'risk' => 2, 'capacity' => 2],
            ['time' => 60, 'risk' => 3, 'capacity' => 4],
            ['time' => 25, 'risk' => 4, 'capacity' => 1],
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

/**
 * Evolve a dispatch policy using GA.
 */
function evolve_policy(int $seed): array
{
    mt_srand($seed);

    $populationSize = 12;
    $generations    = 12;
    $mutationRate   = 0.2;
    $crossoverRate  = 0.5;

    $population = new Population();
    $fitness    = new DispatchPolicyFitness();
    $mutation   = new RandomMutation($mutationRate);
    $crossover  = new UniformCrossover($crossoverRate);

    $population->initialize($populationSize, function () {
        $individual = new DispatchPolicyChromosome();
        $individual->assign([
            'risk_weight'   => mt_rand(0, 100) / 50.0,
            'time_weight'   => mt_rand(0, 100) / 50.0,
            'capacity_bias' => mt_rand(0, 100) / 50.0,
        ]);

        return $individual;
    });

    $best = null;

    for ($gen = 0; $gen < $generations; $gen++) {
        $individuals = $population->getIndividuals();

        $scored = [];
        foreach ($individuals as $ind) {
            $scored[] = [
                'fitness' => $fitness->evaluate($ind),
                'policy'  => $ind->data(),
            ];
        }

        usort($scored, function ($a, $b) {
            return $b['fitness'] <=> $a['fitness'];
        });

        $best = $scored[0];

        $population = $population->selection(function (array $pool) use ($fitness) {
            usort($pool, function ($a, $b) use ($fitness) {
                return $fitness->evaluate($b) <=> $fitness->evaluate($a);
            });

            return array_slice($pool, 0, (int)ceil(count($pool) / 2));
        });

        $parents = $population->getIndividuals();
        $nextGen = $parents;

        while (count($nextGen) < $populationSize) {
            $p1 = $parents[array_rand($parents)];
            $p2 = $parents[array_rand($parents)];

            $offspring = $crossover->cross($p1, $p2);
            $nextGen[] = $offspring;
        }

        $population = new Population($nextGen);
        $population->mutate(function ($individual) use ($mutation) {
            $mutation->mutate($individual);
        });
    }

    return [
        'fitness' => $best['fitness'] ?? 0.0,
        'policy'  => $best['policy'] ?? [],
    ];
}

// Build a payoff matrix for joint actions.
$payoffMatrix = new PayoffMatrix();
$payoffMatrix->setPayoff(['Conservative', 'Conservative'], [8, 7]);
$payoffMatrix->setPayoff(['Conservative', 'Aggressive'],   [5, 9]);
$payoffMatrix->setPayoff(['Aggressive',   'Conservative'], [9, 5]);
$payoffMatrix->setPayoff(['Aggressive',   'Aggressive'],   [4, 4]);

/**
 * Simulation entities.
 */
class RoadConditionEntity implements ISimulatable
{
    public function step(int $tick, array &$worldState): void
    {
        $index = $worldState['road_condition_index'] ?? 1.0;

        if ($tick < 5) {
            $index += 0.5;
        } elseif ($tick >= 10) {
            $index = max(0.0, $index - 0.5);
        }

        $worldState['road_condition_index'] = $index;
    }
}

class DemandEntity implements ISimulatable
{
    public function step(int $tick, array &$worldState): void
    {
        $base = $worldState['demand_level'] ?? 0.0;
        $increment = ($tick < 8) ? 5.0 : 2.0;

        $worldState['demand_level'] = $base + $increment;
    }
}

/**
 * DispatchDecisionEntity
 *
 * Uses the GA-evolved policy and the payoff matrix to choose
 * conservative vs aggressive behavior and accumulates metrics.
 */
class DispatchDecisionEntity implements ISimulatable
{
    private array $policy;
    private PayoffMatrix $payoffMatrix;

    public function __construct(array $policy, PayoffMatrix $payoffMatrix)
    {
        $this->policy = $policy;
        $this->payoffMatrix = $payoffMatrix;
    }

    public function step(int $tick, array &$worldState): void
    {
        $road   = $worldState['road_condition_index'] ?? 1.0;
        $demand = $worldState['demand_level'] ?? 0.0;

        $riskWeight   = (float)($this->policy['risk_weight'] ?? 1.0);
        $timeWeight   = (float)($this->policy['time_weight'] ?? 1.0);
        $capacityBias = (float)($this->policy['capacity_bias'] ?? 1.0);

        // Simple heuristic: higher road risk & demand push towards conservative.
        $riskScore = $riskWeight * $road;
        $urgency   = $timeWeight * ($demand / 50.0);

        $logisticsAction = ($riskScore > $urgency) ? 'Conservative' : 'Aggressive';
        $hospitalAction  = ($demand > 30.0) ? 'Aggressive' : 'Conservative';

        $payoff = $this->payoffMatrix->getPayoff([$logisticsAction, $hospitalAction]) ?? [0, 0];

        $worldState['metrics']['total_logistics_utility'] =
            ($worldState['metrics']['total_logistics_utility'] ?? 0.0) + $payoff[0];

        $worldState['metrics']['total_hospital_utility'] =
            ($worldState['metrics']['total_hospital_utility'] ?? 0.0) + $payoff[1];

        $worldState['decisions'][] = [
            'tick'       => $tick,
            'road'       => $road,
            'demand'     => $demand,
            'actions'    => [
                'logistics' => $logisticsAction,
                'hospital'  => $hospitalAction,
            ],
            'payoff'     => [
                'logistics' => $payoff[0],
                'hospital'  => $payoff[1],
            ],
        ];
    }
}

// Evolve a policy once for this run.
$best = evolve_policy($seed);

// Build the simulation.
$sim = new Simulation($steps);
$sim->addEntity(new RoadConditionEntity());
$sim->addEntity(new DemandEntity());
$sim->addEntity(new DispatchDecisionEntity($best['policy'], $payoffMatrix));

$initialState = [
    'road_condition_index' => 1.0,
    'demand_level'         => 0.0,
    'metrics'              => [
        'total_logistics_utility' => 0.0,
        'total_hospital_utility'  => 0.0,
    ],
    'decisions'            => [],
];

$timeline = $sim->run($initialState);

$summary = end($timeline);

echo json_encode([
    'seed'        => $seed,
    'steps'       => $steps,
    'best_policy' => $best,
    'timeline'    => $timeline,
    'summary'     => [
        'total_logistics_utility' => $summary['metrics']['total_logistics_utility'] ?? null,
        'total_hospital_utility'  => $summary['metrics']['total_hospital_utility'] ?? null,
        'final_road_condition'    => $summary['road_condition_index'] ?? null,
        'final_demand_level'      => $summary['demand_level'] ?? null,
        'decisions_logged'        => count($summary['decisions'] ?? []),
    ],
], JSON_PRETTY_PRINT) . PHP_EOL;

