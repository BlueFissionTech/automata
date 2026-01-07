<?php

namespace BlueFission\Tests\Automata\Simulation;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Simulation\Simulation;
use BlueFission\Automata\Simulation\ISimulatable;

class CounterEntity implements ISimulatable
{
    public function step(int $tick, array &$worldState): void
    {
        $worldState['counter'] = ($worldState['counter'] ?? 0) + 1;
    }
}

class SimulationTest extends TestCase
{
    public function testSimulationAdvancesWorldStateOverTicks(): void
    {
        $sim = new Simulation(5);
        $sim->addEntity(new CounterEntity());

        $log = $sim->run(['counter' => 0]);

        $this->assertCount(5, $log);

        $final = end($log);
        $this->assertSame(5, $final['counter']);
        $this->assertSame(4, $final['tick']);
    }
}

