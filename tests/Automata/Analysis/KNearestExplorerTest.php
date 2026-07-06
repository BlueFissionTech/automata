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

    public function testNeighborsHandlesEmptySamplesAndNegativeLimit(): void
    {
        $explorer = new KNearestExplorer();

        $this->assertSame([], $explorer->neighbors([1, 1], 2));
        $this->assertSame([], $explorer->neighbors([1, 1], -1));
    }

    public function testSetDataReindexesSamplesWithDefaultIds(): void
    {
        $explorer = new KNearestExplorer([[100, 100]], ['stale']);

        $explorer->setData([
            [2, 2],
            [0, 0],
        ]);

        $neighbors = $explorer->neighbors([0, 0], 2);

        $this->assertSame(1, $neighbors[0]['id']);
        $this->assertSame(0, $neighbors[1]['id']);
    }
}
