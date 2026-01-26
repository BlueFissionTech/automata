<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\Path\Graph;
use BlueFission\Automata\Path\Node;
use BlueFission\Automata\Path\RoutePlanner;

/**
 * Graph routing example in the disaster logistics domain.
 *
 * Scenario:
 * - Hub-1 needs to reach Hospital-A.
 * - Two routes exist: via Bridge-1 (faster but high risk) and via Highway-Loop
 *   (slower but safer).
 * - We compute best routes under a time + risk fitness function and then
 *   simulate blocked edges to demonstrate rerouting.
 */

function buildLogisticsGraph(bool $blockHighway = false): Graph
{
    $graph = new Graph();

    $hubEdges = [
        'Bridge-1' => ['time' => 20, 'risk' => 4],
        'Highway-Loop' => ['time' => 35, 'risk' => 1],
    ];

    if ($blockHighway) {
        $hubEdges['Highway-Loop']['blocked'] = true;
    }

    $hub = new Node('Hub-1', $hubEdges);

    $bridge = new Node('Bridge-1', [
        'Hospital-A' => ['time' => 5, 'risk' => 4],
    ]);

    $highwayEdges = [
        'Hospital-A' => ['time' => 10, 'risk' => 1],
    ];

    if ($blockHighway) {
        $highwayEdges['Hospital-A']['blocked'] = true;
    }

    $highway = new Node('Highway-Loop', $highwayEdges);

    $hospital = new Node('Hospital-A', []);

    $graph->addNode($hub);
    $graph->addNode($bridge);
    $graph->addNode($highway);
    $graph->addNode($hospital);

    return $graph;
}

$fitness = function (array $edge): int {
    if (!empty($edge['blocked'])) {
        return PHP_INT_MAX / 4;
    }

    $time = $edge['time'] ?? 0;
    $risk = $edge['risk'] ?? 0;

    return $time + $risk * 20;
};

echo "=== Graph Routing Logistics Example ===\n\n";

echo "Baseline world (no blocked edges):\n";
$graph = buildLogisticsGraph(false);
$planner = new RoutePlanner($graph, $fitness);
$route = $planner->plan('Hub-1', 'Hospital-A');

if ($route) {
    echo "- Planned path: " . implode(' -> ', $route->getPath()) . "\n";
    echo "- Cost: " . $route->getCost() . "\n\n";
} else {
    echo "- No route available.\n\n";
}

echo "World with highway route blocked:\n";
$graphBlocked = buildLogisticsGraph(true);
$plannerBlocked = new RoutePlanner($graphBlocked, $fitness);
$routeBlocked = $plannerBlocked->plan('Hub-1', 'Hospital-A');

if ($routeBlocked) {
    echo "- Planned path: " . implode(' -> ', $routeBlocked->getPath()) . "\n";
    echo "- Cost: " . $routeBlocked->getCost() . "\n\n";
} else {
    echo "- No route available.\n\n";
}

echo "Example completed.\n";

