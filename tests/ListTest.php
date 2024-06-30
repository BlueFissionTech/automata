<?php

namespace BlueFission\Tests;

use PHPUnit\Framework\TestCase;
use BlueFission\List;
use Ds\Set;

class ListTest extends TestCase
{
    public function testCanInstantiateList(): void
    {
        $list = new List();
        $this->assertInstanceOf(List::class, $list);
        $this->assertInstanceOf(Set::class, $list->cast()->_data);
    }

    public function testAddAndHasElement(): void
    {
        $list = new List();
        $list->add('element');

        $this->assertTrue($list->has('element'));
    }

    public function testRemoveElement(): void
    {
        $list = new List(['element', 'other']);
        $list->remove('element');

        $this->assertFalse($list->has('element'));
        $this->assertTrue($list->has('other'));
    }

    public function testClearList(): void
    {
        $list = new List(['element', 'other']);
        $list->clear();

        $this->assertEquals(0, $list->count());
    }

    public function testCountElementsInList(): void
    {
        $list = new List(['a', 'b', 'c']);
        $this->assertEquals(3, $list->count());
    }

    public function testUnionOfSets(): void
    {
        $list = new List([1, 2, 3]);
        $otherSet = new Set([3, 4, 5]);

        $union = $list->union($otherSet);
        $expectedUnion = new Set([1, 2, 3, 4, 5]);

        $this->assertEquals($expectedUnion, $union);
    }

    public function testIntersectionOfSets(): void
    {
        $list = new List([1, 2, 3, 4]);
        $otherSet = new Set([3, 4, 5]);

        $intersection = $list->intersect($otherSet);
        $expectedIntersection = new Set([3, 4]);

        $this->assertEquals($expectedIntersection, $intersection);
    }

    public function testDifferenceOfSets(): void
    {
        $list = new List([1, 2, 3, 4]);
        $otherSet = new Set([3, 4, 5]);

        $difference = $list->diff($otherSet);
        $expectedDifference = new Set([1, 2]);

        $this->assertEquals($expectedDifference, $difference);
    }
}
