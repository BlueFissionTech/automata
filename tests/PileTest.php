<?php

namespace BlueFission\Tests;

use PHPUnit\Framework\TestCase;
use BlueFission\Pile;
use Ds\Stack;

class PileTest extends TestCase
{
    public function testCanInstantiatePile(): void
    {
        $pile = new Pile();
        $this->assertInstanceOf(Pile::class, $pile);
        $this->assertInstanceOf(Stack::class, $pile->cast()->_data);
    }

    public function testPushAndPeekElement(): void
    {
        $pile = new Pile();
        $pile->push('first');
        $pile->push('second');

        $this->assertEquals('second', $pile->peek());
    }

    public function testPopElement(): void
    {
        $pile = new Pile(['first', 'second']);
        $top = $pile->pop();

        $this->assertEquals('second', $top);
        $this->assertEquals(1, $pile->count());
        $this->assertEquals('first', $pile->peek());
    }

    public function testIsEmpty(): void
    {
        $pile = new Pile();
        $this->assertTrue($pile->isEmpty());

        $pile->push('element');
        $this->assertFalse($pile->isEmpty());
    }

    public function testClearPile(): void
    {
        $pile = new Pile(['one', 'two', 'three']);
        $pile->clear();

        $this->assertTrue($pile->isEmpty());
        $this->assertEquals(0, $pile->count());
    }

    public function testCountElementsInPile(): void
    {
        $pile = new Pile(['a', 'b', 'c']);
        $this->assertEquals(3, $pile->count());
    }
}
