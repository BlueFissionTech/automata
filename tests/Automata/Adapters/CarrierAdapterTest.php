<?php

namespace BlueFission\Tests\Automata\Adapters;

use BlueFission\Automata\Adapters\CarrierAdapter;
use BlueFission\Obj;
use PHPUnit\Framework\TestCase;

class CarrierAdapterTest extends TestCase
{
    public function testCarrierAdapterWrapsObjFieldAccess(): void
    {
        $carrier = new class extends Obj {
        };

        $adapter = new CarrierAdapter($carrier);
        $adapter
            ->field('name', 'Transit Domain')
            ->field('meta', ['priority' => 'high'])
            ->assign(['status' => 'ready']);

        $this->assertSame('Transit Domain', $carrier->field('name'));
        $this->assertSame('ready', $adapter->field('status'));
        $this->assertSame('high', $adapter->snapshot()['meta']['priority']);
    }
}
