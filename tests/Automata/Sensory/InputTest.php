<?php

namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Automata\Sensory\Input;
use BlueFission\Behavioral\Behaviors\Event;
use PHPUnit\Framework\TestCase;

class InputTest extends TestCase
{
    private $input;

    protected function setUp(): void
    {
        $this->input = new Input();
    }

    public function testConstructorDefaultProcessor()
    {
        $reflection = new \ReflectionClass($this->input);
        $processorsProperty = $reflection->getProperty('_processors');
        $processorsProperty->setAccessible(true);
        $processors = $processorsProperty->getValue($this->input);

        $this->assertCount(1, $processors);
    }

    public function testSetAndGetName()
    {
        $this->input->name('TestInput');
        $this->assertEquals('TestInput', $this->input->name());
    }

    public function testSetProcessor()
    {
        $processor = function($data) {
            return $data . ' processed';
        };
        $this->input->setProcessor($processor);

        $reflection = new \ReflectionClass($this->input);
        $processorsProperty = $reflection->getProperty('_processors');
        $processorsProperty->setAccessible(true);
        $processors = $processorsProperty->getValue($this->input);

        $this->assertCount(2, $processors);
    }

    public function testScanWithDefaultProcessor()
    {
        $processedData = null;

        $this->input->on(Event::COMPLETE, function ($event) use (&$processedData) {
            $processedData = $event->context;
        });

        $this->input->scan('TestData');

        $this->assertEquals('TestData', $processedData);
    }

    public function testScanWithCustomProcessor()
    {
        $processedData = null;

        $this->input->setProcessor(function($data) {
            return $data . ' processed';
        });

        $this->input->on(Event::COMPLETE, function ($event) use (&$processedData) {
            $processedData = $event->context;
        });

        $this->input->scan('TestData');

        $this->assertEquals('TestData processed', $processedData);
    }

    public function testDispatchCustomBehavior()
    {
        $behaviorTriggered = false;

        $this->input->behavior('customBehavior', function() use (&$behaviorTriggered) {
            $behaviorTriggered = true;
        });

        $this->input->dispatch('customBehavior');

        $this->assertTrue($behaviorTriggered);
    }

    public function testDispatchEventWithArgs()
    {
        $eventData = null;

        $this->input->on('customBehavior', function ($behavior) use (&$eventData) {
            $eventData = $behavior->context;
        });

        $this->input->dispatch('customBehavior', 'TestData');

        $this->assertEquals('TestData', $eventData);
    }
}
