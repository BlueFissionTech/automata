<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
automata_example_require('Automata/Path/RouteAllocator.php');

use BlueFission\Func;
use BlueFission\Automata\Path\Graph;
use BlueFission\Automata\Path\Node;
use BlueFission\Automata\Path\RouteAllocator;

/**
 * Graph route allocation example (logistics).
 *
 * Scenario:
 * - Two trucks depart from Hub-1.
 * - Both need to deliver supplies to Shelter-1.
 * - Two routes exist: via Bridge-1 (higher risk, lower capacity)
 *   and via Highway-Loop (lower risk, higher capacity).
 *
 * We allocate flow while respecting edge capacities and
 * preferring safer routes, with a fitness hook that can
 * see the asset and demand context.
 */

function buildLogisticsGraph(): Graph
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

$graph = buildLogisticsGraph();

$fitness = new Func(function (array $edge, array $asset, array $demand): float {
    if (!empty($edge['blocked'])) {
        return (float)(PHP_INT_MAX / 4);
    }

    $time = $edge['time'] ?? 0;
    $risk = $edge['risk'] ?? 0;

    $urgencyPenalty = (($demand['priority'] ?? 0) >= 10 && $time > 25) ? 10.0 : 0.0;
    $capacityBias = (($asset['capacity'] ?? 0.0) >= 4.0) ? -5.0 : 0.0;

    return (float)($time + $risk * 20 + $urgencyPenalty + $capacityBias);
});

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

echo "=== Graph Route Allocation Logistics Example ===\n\n";
echo "Fitness hook considers: edge risk/time, demand priority, and asset capacity.\n\n";

foreach ($allocations as $alloc) {
    $assetId = $alloc['asset_id'];
    $demandId = $alloc['demand_id'];
    $path = implode(' -> ', $alloc['path']);
    $amount = $alloc['amount'];

    echo "Asset {$assetId} -> Demand {$demandId}: path={$path}, amount={$amount}\n";
}

echo "\nExample completed.\n";

