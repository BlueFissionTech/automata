<?php

namespace BlueFission\Tests;

use PHPUnit\Framework\TestCase;
use BlueFission\Set;

class ListTest extends TestCase
{
    public function testCanInstantiateList(): void
    {
        $list = new Set();
        $this->assertInstanceOf(Set::class, $list);
        $list->cast();

        $ref = new \ReflectionClass($list);
        $prop = $ref->getProperty('_data');
        $prop->setAccessible(true);
        $data = $prop->getValue($list);

        if (extension_loaded('ds') && class_exists('\Ds\Set')) {
            $this->assertInstanceOf('\Ds\Set', $data);
        } else {
            $this->assertIsArray($data);
        }
    }

    public function testAddAndHasElement(): void
    {
        $list = new Set();
        $list->add('element');

        $this->assertTrue($list->has('element'));
    }

    public function testRemoveElement(): void
    {
        $list = new Set(['element', 'other']);
        $list->remove('element');

        $this->assertFalse($list->has('element'));
        $this->assertTrue($list->has('other'));
    }

    public function testClearList(): void
    {
        $list = new Set(['element', 'other']);
        $list->clear();

        $this->assertEquals(0, $list->count());
    }

    public function testCountElementsInList(): void
    {
        $list = new Set(['a', 'b', 'c']);
        $this->assertEquals(3, $list->count());
    }

    public function testUnionOfSets(): void
    {
        $list = new Set([1, 2, 3]);
        $otherSet = extension_loaded('ds') && class_exists('\Ds\Set')
            ? new \Ds\Set([3, 4, 5])
            : [3, 4, 5];

        $union = $list->union($otherSet);
        $expectedUnion = [1, 2, 3, 4, 5];

        $this->assertEqualsCanonicalizing($expectedUnion, is_array($union) ? $union : $union->toArray());
    }

    public function testIntersectionOfSets(): void
    {
        $list = new Set([1, 2, 3, 4]);
        $otherSet = extension_loaded('ds') && class_exists('\Ds\Set')
            ? new \Ds\Set([3, 4, 5])
            : [3, 4, 5];

        $intersection = $list->intersect($otherSet);
        $expectedIntersection = [3, 4];

        $this->assertEqualsCanonicalizing($expectedIntersection, is_array($intersection) ? $intersection : $intersection->toArray());
    }

    public function testDifferenceOfSets(): void
    {
        $list = new Set([1, 2, 3, 4]);
        $otherSet = extension_loaded('ds') && class_exists('\Ds\Set')
            ? new \Ds\Set([3, 4, 5])
            : [3, 4, 5];

        $difference = $list->diff($otherSet);
        $expectedDifference = [1, 2];

        $this->assertEqualsCanonicalizing($expectedDifference, is_array($difference) ? $difference : $difference->toArray());
    }
}
