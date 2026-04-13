<?php

namespace BlueFission\Tests\Automata\Adapters;

use BlueFission\Automata\Adapters\StateAdapter;
use BlueFission\Obj;
use PHPUnit\Framework\TestCase;

class StateAdapterTest extends TestCase
{
    public function testStateAdapterReadsAndWritesObjBackedState(): void
    {
        $carrier = new class extends Obj {
        };

        $carrier->assign([
            'world' => [
                'risk' => 2,
                'zone' => 'north',
            ],
            'status' => 'ready',
        ]);

        $adapter = new StateAdapter($carrier);

        $this->assertSame(2, $adapter->get('world.risk'));

        $adapter
            ->set('world.risk', 1)
            ->set('world.last_update', 'tick-3')
            ->sync();

        $world = $carrier->field('world');

        $this->assertSame(1, $world['risk']);
        $this->assertSame('tick-3', $world['last_update']);
    }
}
