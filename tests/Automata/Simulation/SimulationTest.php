<?php

namespace BlueFission\Tests\Automata\Simulation;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Simulation\Simulation;
use BlueFission\Automata\Simulation\ISimulatable;
use BlueFission\Obj;

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

    public function testSimulationCanAdvanceObjectBackedState(): void
    {
        $state = new class extends Obj {
        };
        $state->assign(['counter' => 0]);

        $sim = new Simulation(3);
        $sim->addEntity(new CounterEntity());

        $log = $sim->run($state);

        $this->assertCount(3, $log);
        $this->assertSame(3, $state->field('counter'));
        $this->assertSame(2, $state->field('tick'));
    }
}

