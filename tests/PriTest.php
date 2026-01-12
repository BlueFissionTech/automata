<?php

namespace BlueFission\Tests;

use PHPUnit\Framework\TestCase;
use BlueFission\Pri;
use Ds\PriorityQueue;

class PriTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('ds')) {
            $this->markTestSkipped('The ds extension is required for Pri tests.');
        }
    }

    public function testCanInstantiatePri(): void
    {
        $pri = new Pri();
        $this->assertInstanceOf(Pri::class, $pri);
        $pri->cast();

        $ref = new \ReflectionClass($pri);
        $prop = $ref->getProperty('_data');
        $prop->setAccessible(true);
        $this->assertInstanceOf(PriorityQueue::class, $prop->getValue($pri));
    }

    public function testInsertElementIntoPri(): void
    {
        $pri = new Pri();
        $pri->insert('high', 100);
        $pri->insert('low', 1);

        $this->assertEquals(2, $pri->count());
        $this->assertEquals('high', $pri->peek());
    }

    public function testExtractElementFromPri(): void
    {
        $pri = new Pri();
        $pri->insert('high', 100);
        $pri->insert('medium', 50);
        $pri->insert('low', 1);

        $extracted = $pri->extract();

        $this->assertEquals('high', $extracted);
        $this->assertEquals(2, $pri->count());
    }

    public function testPeekDoesNotRemoveElement(): void
    {
        $pri = new Pri();
        $pri->insert('only', 50);

        $peeked = $pri->peek();

        $this->assertEquals('only', $peeked);
        $this->assertEquals(1, $pri->count());
    }

    public function testIsEmpty(): void
    {
        $pri = new Pri();
        $this->assertTrue($pri->isEmpty());

        $pri->insert('element', 10);
        $this->assertFalse($pri->isEmpty());
    }

    public function testClearPri(): void
    {
        $pri = new Pri();
        $pri->insert('one', 1);
        $pri->insert('two', 2);

        $pri->clear();
        $this->assertTrue($pri->isEmpty());
    }
}
