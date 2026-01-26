<?php

namespace BlueFission\Tests\Automata\Path;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Path\Graph;
use BlueFission\Automata\Path\Node;

class GraphRoutingTest extends TestCase
{
    private function buildBaseGraph(): Graph
    {
        $graph = new Graph();

        // Hub connects to two alternative routes toward Hospital.
        $hub = new Node('Hub', [
            'Bridge' => ['time' => 20, 'risk' => 4],
            'Highway' => ['time' => 35, 'risk' => 1],
        ]);

        $bridge = new Node('Bridge', [
            'Hospital' => ['time' => 5, 'risk' => 4],
        ]);

        $highway = new Node('Highway', [
            'Hospital' => ['time' => 10, 'risk' => 1],
        ]);

        $hospital = new Node('Hospital', []);

        $graph->addNode($hub);
        $graph->addNode($bridge);
        $graph->addNode($highway);
        $graph->addNode($hospital);

        return $graph;
    }

    public function testShortestPathPrefersSaferRouteUnderWeightedFitness(): void
    {
        $graph = $this->buildBaseGraph();

        $fitness = function (array $edge): int {
            $time = $edge['time'] ?? 0;
            $risk = $edge['risk'] ?? 0;

            return $time + $risk * 20;
        };

        $path = $graph->shortestPath('Hub', 'Hospital', $fitness);

        $this->assertSame(['Hub', 'Highway', 'Hospital'], $path);
    }

    public function testBlockedEdgeForcesReroute(): void
    {
        // Same topology but Highway edges are marked blocked.
        $graph = new Graph();

        $hub = new Node('Hub', [
            'Bridge' => ['time' => 20, 'risk' => 4],
            'Highway' => ['time' => 35, 'risk' => 1, 'blocked' => true],
        ]);

        $bridge = new Node('Bridge', [
            'Hospital' => ['time' => 5, 'risk' => 4],
        ]);

        $highway = new Node('Highway', [
            'Hospital' => ['time' => 10, 'risk' => 1, 'blocked' => true],
        ]);

        $hospital = new Node('Hospital', []);

        $graph->addNode($hub);
        $graph->addNode($bridge);
        $graph->addNode($highway);
        $graph->addNode($hospital);

        $fitness = function (array $edge): int {
            if (!empty($edge['blocked'])) {
                return PHP_INT_MAX / 4;
            }

            $time = $edge['time'] ?? 0;
            $risk = $edge['risk'] ?? 0;

            return $time + $risk * 20;
        };

        $path = $graph->shortestPath('Hub', 'Hospital', $fitness);

        $this->assertSame(['Hub', 'Bridge', 'Hospital'], $path);
    }

    public function testNoPathReturnsEmptyArray(): void
    {
        $graph = new Graph();

        $a = new Node('A', ['B' => ['time' => 5, 'risk' => 1]]);
        $b = new Node('B', []);
        $c = new Node('C', []); // disconnected

        $graph->addNode($a);
        $graph->addNode($b);
        $graph->addNode($c);

        $fitness = function (array $edge): int {
            return ($edge['time'] ?? 0) + ($edge['risk'] ?? 0) * 10;
        };

        $path = $graph->shortestPath('A', 'C', $fitness);

        $this->assertSame([], $path);
    }
}

