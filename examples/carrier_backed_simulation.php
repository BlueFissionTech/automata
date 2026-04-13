<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
automata_example_require('Automata/Simulation/Simulation.php');

use BlueFission\Obj;
use BlueFission\Num;
use BlueFission\Automata\Simulation\Simulation;
use BlueFission\Automata\Simulation\ISimulatable;

class QueuePressureEntity implements ISimulatable
{
    public function step(int $tick, array &$worldState): void
    {
        $queue = $worldState['queue'] ?? 0;
        $processed = ($tick % 2 === 0) ? 1 : 2;

        $queue = Num::add($queue, 3);
        $queue = Num::max(0, Num::sub($queue, $processed));

        $worldState['queue'] = $queue;
        $worldState['status'] = $queue > 4 ? 'congested' : 'stable';
    }
}

$state = new class extends Obj {
};
$state->assign([
    'queue' => 1,
    'status' => 'stable',
]);

$simulation = new Simulation(4);
$simulation->addEntity(new QueuePressureEntity());

$timeline = $simulation->run($state);

echo json_encode([
    'timeline' => $timeline,
    'final_state' => $state->toArray(),
], JSON_PRETTY_PRINT) . PHP_EOL;
