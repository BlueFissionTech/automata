<?php

namespace BlueFission\Tests;

use PHPUnit\Framework\TestCase;
use BlueFission\Deq;
use Ds\Deque;

class DeqTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('ds')) {
            $this->markTestSkipped('The ds extension is required for Deq tests.');
        }
    }

    public function testCanInstantiateDeq(): void
    {
        $deq = new Deq();
        $this->assertInstanceOf(Deq::class, $deq);
        $deq->cast();

        $ref = new \ReflectionClass($deq);
        $prop = $ref->getProperty('_data');
        $prop->setAccessible(true);
        $this->assertInstanceOf(Deque::class, $prop->getValue($deq));
    }

    public function testPushAndPopFront(): void
    {
        $deq = new Deq();
        $deq->pushFront('first');
        $deq->pushFront('new first');

        $this->assertEquals('new first', $deq->popFront());
        $this->assertEquals('first', $deq->popFront());
    }

    public function testPushAndPopBack(): void
    {
        $deq = new Deq();
        $deq->pushBack('first');
        $deq->pushBack('new last');

        $this->assertEquals('new last', $deq->popBack());
        $this->assertEquals('first', $deq->popBack());
    }

    public function testGetAndSet(): void
    {
        $deq = new Deq(['first', 'second', 'third']);
        $this->assertEquals('second', $deq->get(1));

        $deq->set(1, 'changed');
        $this->assertEquals('changed', $deq->get(1));
    }

    public function testIsEmpty(): void
    {
        $deq = new Deq();
        $this->assertTrue($deq->isEmpty());

        $deq->pushBack('element');
        $this->assertFalse($deq->isEmpty());
    }

    public function testClearDeq(): void
    {
        $deq = new Deq(['one', 'two', 'three']);
        $deq->clear();
        $this->assertTrue($deq->isEmpty());
    }

    public function testCountElementsInDeq(): void
    {
        $deq = new Deq(['a', 'b', 'c']);
        $this->assertEquals(3, $deq->count());
    }
}
