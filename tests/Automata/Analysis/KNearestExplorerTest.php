<?php

namespace BlueFission\Tests\Automata\Analysis;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Analysis\KNearestExplorer;

class KNearestExplorerTest extends TestCase
{
    public function testNeighborsReturnExpectedIdsAndDistances(): void
    {
        $samples = [
            [0, 0],
            [1, 1],
            [2, 2],
        ];

        $ids = ['a', 'b', 'c'];

        $explorer = new KNearestExplorer($samples, $ids);

        $neighbors = $explorer->neighbors([0.5, 0.5], 2);

        $this->assertCount(2, $neighbors);
        $this->assertSame('a', $neighbors[0]['id']);
        $this->assertSame('b', $neighbors[1]['id']);
    }
}
