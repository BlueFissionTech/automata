<?php

namespace BlueFission\Tests;

use PHPUnit\Framework\TestCase;
use BlueFission\Vec;
use Ds\Vector;

class VecTest extends TestCase
{
    public function testCanInstantiateVec(): void
    {
        $vec = new Vec();
        $this->assertInstanceOf(Vec::class, $vec);
        $this->assertInstanceOf(Vector::class, $vec->cast()->_data); // Ensure the data is indeed a Vector
    }

    public function testAddElementToVec(): void
    {
        $vec = new Vec();
        $vec->add('test');
        
        $this->assertEquals('test', $vec->get(0));
    }

    public function testRemoveElementFromVec(): void
    {
        $vec = new Vec(['first', 'second', 'third']);
        $vec->remove(1); // Remove 'second'
        
        $this->assertEquals(2, $vec->count());
        $this->assertEquals('third', $vec->get(1));
    }

    public function testSetElementInVec(): void
    {
        $vec = new Vec([1, 2, 3]);
        $vec->set(1, 'replaced');
        
        $this->assertEquals('replaced', $vec->get(1));
    }

    public function testClearVec(): void
    {
        $vec = new Vec([1, 2, 3]);
        $vec->clear();
        
        $this->assertEquals(0, $vec->count());
    }

    public function testCountElementsInVec(): void
    {
        $vec = new Vec(['a', 'b', 'c']);
        
        $this->assertEquals(3, $vec->count());
    }

    public function testIsVector(): void
    {
        $vec = new Vec();
        
        $this->assertTrue($vec->_is());
    }
}

