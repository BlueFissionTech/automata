<?php

declare(strict_types=1);

use BlueFission\DevElation;
use BlueFission\Automata\Simulation\Simulation;
use BlueFission\Automata\Simulation\ISimulatable;

require __DIR__ . '/../../../vendor/autoload.php';

$seed = 123;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--seed=')) {
        $seed = (int)substr($arg, strlen('--seed='));
    }
}

mt_srand($seed);
DevElation::up();

/**
 * Simple world simulation:
 *
 * - RoadConditionEntity: updates a scalar road condition index over time.
 * - DemandEntity: models rising demand for supplies at hospitals/shelters.
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

$ticks = 15;
$sim   = new Simulation($ticks);
$sim->addEntity(new RoadConditionEntity());
$sim->addEntity(new DemandEntity());

$initial = [
    'road_condition_index' => 1.0,
    'demand_level'         => 0.0,
];

$log = $sim->run($initial);

echo json_encode([
    'seed' => $seed,
    'ticks' => $ticks,
    'timeline' => $log,
], JSON_PRETTY_PRINT) . PHP_EOL;

