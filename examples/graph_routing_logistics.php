<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
automata_example_require('Automata/Path/RoutePlanner.php');

use BlueFission\Func;
use BlueFission\Obj;
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
 * - We compute best routes under a time + risk fitness function.
 * - We then apply a state-aware assessor that penalizes risky edges more
 *   aggressively during rain-sensitive medical runs.
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

$worldState = new class extends Obj {
};
$worldState->assign([
    'weather' => 'rain',
    'mission' => 'medical',
]);

$fitness = new Func(function (array $edge): int {
    if (!empty($edge['blocked'])) {
        return intdiv(PHP_INT_MAX, 4);
    }

    $time = $edge['time'] ?? 0;
    $risk = $edge['risk'] ?? 0;

    return $time + $risk * 20;
});

$assessor = new Func(function (array $edge, array $state, array $context): int {
    if (!empty($edge['blocked'])) {
        return intdiv(PHP_INT_MAX, 4);
    }

    $time = $edge['time'] ?? 0;
    $risk = $edge['risk'] ?? 0;
    $score = $time + $risk * 20;

    if (($state['weather'] ?? null) === 'rain' && $risk >= 4) {
        $score += 25;
    }

    if (($context['priority'] ?? null) === 'life_safety' && $time > 25) {
        $score += 10;
    }

    return $score;
});

echo "=== Graph Routing Logistics Example ===\n\n";
echo "World state:\n";
echo "- weather: " . $worldState->field('weather') . "\n";
echo "- mission: " . $worldState->field('mission') . "\n\n";

echo "Baseline world (no blocked edges):\n";
$graph = buildLogisticsGraph(false);
$planner = new RoutePlanner($graph, $fitness, $worldState, $assessor);
$route = $planner->plan('Hub-1', 'Hospital-A', [
    'priority' => 'life_safety',
]);

if ($route) {
    echo "- Planned path: " . implode(' -> ', $route->getPath()) . "\n";
    echo "- Cost: " . $route->getCost() . "\n\n";
} else {
    echo "- No route available.\n\n";
}

echo "World with highway route blocked:\n";
$graphBlocked = buildLogisticsGraph(true);
$plannerBlocked = new RoutePlanner($graphBlocked, $fitness, $worldState, $assessor);
$routeBlocked = $plannerBlocked->plan('Hub-1', 'Hospital-A', [
    'priority' => 'life_safety',
]);

if ($routeBlocked) {
    echo "- Planned path: " . implode(' -> ', $routeBlocked->getPath()) . "\n";
    echo "- Cost: " . $routeBlocked->getCost() . "\n\n";
} else {
    echo "- No route available.\n\n";
}

echo "Example completed.\n";

