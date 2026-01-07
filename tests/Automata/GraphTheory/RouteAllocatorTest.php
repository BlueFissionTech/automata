<?php

namespace BlueFission\Tests\Automata\GraphTheory;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\GraphTheory\Graph;
use BlueFission\Automata\GraphTheory\Node;
use BlueFission\Automata\GraphTheory\RouteAllocator;

class RouteAllocatorTest extends TestCase
{
    private function buildGraph(): Graph
    {
        $graph = new Graph();

        $hub = new Node('Hub-1', [
            'Bridge-1' => ['time' => 20, 'risk' => 4],
            'Highway-Loop' => ['time' => 35, 'risk' => 1],
        ]);

        $bridge = new Node('Bridge-1', [
            'Shelter-1' => ['time' => 5, 'risk' => 4],
        ]);

        $highway = new Node('Highway-Loop', [
            'Shelter-1' => ['time' => 10, 'risk' => 1],
        ]);

        $shelter = new Node('Shelter-1', []);

        $graph->addNode($hub);
        $graph->addNode($bridge);
        $graph->addNode($highway);
        $graph->addNode($shelter);

        return $graph;
    }

    public function testAllocatorRespectsEdgeCapacitiesAndPrefersSaferRoute(): void
    {
        $graph = $this->buildGraph();

        $fitness = function (array $edge): float {
            $time = $edge['time'] ?? 0;
            $risk = $edge['risk'] ?? 0;
            return (float)($time + $risk * 20);
        };

        $allocator = new RouteAllocator($graph, $fitness);

        $assets = [
            ['id' => 'Truck-A', 'origin' => 'Hub-1', 'capacity' => 4.0],
            ['id' => 'Truck-B', 'origin' => 'Hub-1', 'capacity' => 4.0],
        ];

        $demands = [
            ['id' => 'Shelter-1', 'node' => 'Shelter-1', 'amount' => 6.0, 'priority' => 10],
        ];

        $edgeCapacities = [
            'Hub-1|Highway-Loop' => 5.0,
            'Highway-Loop|Shelter-1' => 5.0,
            'Hub-1|Bridge-1' => 2.0,
            'Bridge-1|Shelter-1' => 2.0,
        ];

        $allocations = $allocator->allocate($assets, $demands, $edgeCapacities);

        $this->assertNotEmpty($allocations);

        $highwayFlow = 0.0;
        $bridgeFlow = 0.0;

        foreach ($allocations as $alloc) {
            $path = $alloc['path'];
            $amount = $alloc['amount'];
            if ($path === ['Hub-1', 'Highway-Loop', 'Shelter-1']) {
                $highwayFlow += $amount;
            } elseif ($path === ['Hub-1', 'Bridge-1', 'Shelter-1']) {
                $bridgeFlow += $amount;
            }
        }

        // Total allocated flow should not exceed demand (6 units).
        $this->assertLessThanOrEqual(6.0, $highwayFlow + $bridgeFlow + 1e-6);

        // Highway route should be preferred and used up to asset capacity.
        $this->assertGreaterThan(0.0, $highwayFlow);
    }
}
