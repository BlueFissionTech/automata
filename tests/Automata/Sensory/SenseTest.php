<?php
namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Automata\Sensory\Sense;
use BlueFission\Behavioral\Behaviors\Event;
use PHPUnit\Framework\TestCase;

class SenseTest extends TestCase
{
    public function testInvokeDispatchesSuccessAndCompleteEvents(): void
    {
        $sense = new Sense();

        $captured = [];
        $sense->behavior(new Event(Event::SUCCESS), function ($behavior) use (&$captured) {
            $captured['success'] = $behavior->context;
        });
        $sense->behavior(new Event(Event::COMPLETE), function ($behavior) use (&$captured) {
            $captured['complete'] = $behavior->context;
        });

        $sense->invoke('test sensory input');

        $this->assertArrayHasKey('success', $captured);
        $this->assertArrayHasKey('complete', $captured);
        $this->assertIsArray($captured['complete']);
        $this->assertArrayHasKey('variance1', $captured['complete']);
    }
}
