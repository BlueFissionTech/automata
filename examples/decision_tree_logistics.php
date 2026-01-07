<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\Node;
use BlueFission\Automata\DecisionTree\DepthFirstMethod;
use BlueFission\Automata\DecisionTree\IMethod;
use BlueFission\Automata\DecisionTree\INode;

/**
 * Disaster logistics decision tree example.
 *
 * Scenario: choose a route from a staging hub to Hospital A during a flood,
 * balancing travel time and risk. Demonstrates DecisionTree + Node +
 * different traversal methods.
 */

// Shared evaluation: higher score is better; time and risk are penalties.
$evaluation = function (array $value, array $children): int {
    $base = 100;
    $timePenalty = $value['time_minutes'] ?? 0;
    $riskPenalty = ($value['risk_level'] ?? 0) * 20;

    return $base - $timePenalty - $riskPenalty;
};

$root = new Node(
    [
        'id' => 'decision_root',
        'description' => 'Select route from Hub-1 to Hospital-A',
        'time_minutes' => 999,
        'risk_level' => 9,
    ],
    $evaluation
);

$routeFastHighRisk = new Node(
    [
        'id' => 'route_fast_high_risk',
        'path' => ['Hub-1', 'Bridge-1', 'Hospital-A'],
        'time_minutes' => 20,
        'risk_level' => 4,
    ],
    $evaluation
);

$routeSlowLowRisk = new Node(
    [
        'id' => 'route_slow_low_risk',
        'path' => ['Hub-1', 'Highway-Loop', 'Hospital-A'],
        'time_minutes' => 35,
        'risk_level' => 1,
    ],
    $evaluation
);

$routeMediumRisk = new Node(
    [
        'id' => 'route_medium_risk',
        'path' => ['Hub-1', 'Service-Road', 'Hospital-A'],
        'time_minutes' => 25,
        'risk_level' => 2,
    ],
    $evaluation
);

$root->addChild($routeFastHighRisk);
$root->addChild($routeSlowLowRisk);
$root->addChild($routeMediumRisk);

$tree = new DecisionTree();
$tree->setRoot($root);

// Method 1: depth-first search for best scoring node.
$depthFirst = new DepthFirstMethod();
$bestDepthFirst = $tree->decide($depthFirst);

// Method 2: trivial "always pick root" method to show IMethod flexibility.
$rootOnlyMethod = new class implements IMethod {
    public function traverse(INode $root): array
    {
        return $root->getValue();
    }
};

$bestRootOnly = $tree->decide($rootOnlyMethod);

echo "=== Decision Tree Logistics Example ===\n\n";

echo "[DepthFirstMethod] Selected route:\n";
echo "- ID: " . $bestDepthFirst['id'] . "\n";
if (isset($bestDepthFirst['path'])) {
    echo "- Path: " . implode(' -> ', $bestDepthFirst['path']) . "\n";
}
echo "- Time (minutes): " . ($bestDepthFirst['time_minutes'] ?? 'n/a') . "\n";
echo "- Risk level: " . ($bestDepthFirst['risk_level'] ?? 'n/a') . "\n\n";

echo "[RootOnlyMethod] Selected node:\n";
echo "- ID: " . $bestRootOnly['id'] . "\n";
echo "- Description: " . ($bestRootOnly['description'] ?? 'n/a') . "\n\n";

echo "Example completed.\n";

