<?php
namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Automata\Sensory\Input;
use BlueFission\Behavioral\Behaviors\Event;
use PHPUnit\Framework\TestCase;

class InputTest extends TestCase
{
    public function testScanAppliesProcessorsAndDispatchesComplete(): void
    {
        $input = new Input(static function ($data) {
            return $data * 2;
        });

        $captured = null;
        $input->behavior(new Event(Event::COMPLETE), function ($behavior) use (&$captured) {
            $captured = $behavior->context;
        });

        $input->scan(5);

        $this->assertSame(10, $captured);
    }
}
