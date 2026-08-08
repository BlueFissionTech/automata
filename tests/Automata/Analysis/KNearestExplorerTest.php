<?php

namespace BlueFission\Tests\Automata\Analysis;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Analysis\KNearestExplorer;
use BlueFission\Tests\Automata\Support\RecordingStructureFactory;

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

    public function testExplorerUsesInjectedStructureFactory(): void
    {
        $factory = new RecordingStructureFactory();
        $explorer = new KNearestExplorer([[0, 0], [1, 1]], null, $factory);

        $neighbors = $explorer->neighbors([0, 0], 1);

        $this->assertSame(0, $neighbors[0]['id']);
        $this->assertGreaterThanOrEqual(1, $factory->valuesCalls);
        $this->assertGreaterThanOrEqual(1, $factory->arrCalls);
    }
}
