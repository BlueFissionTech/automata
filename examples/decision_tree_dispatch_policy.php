<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
automata_example_require(
    'Automata/DecisionTree/INode.php',
    'Automata/DecisionTree/BaseMethod.php',
    'Automata/DecisionTree/Node.php',
    'Automata/DecisionTree/DepthFirstTraceMethod.php'
);

use BlueFission\Func;
use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\Node;
use BlueFission\Automata\DecisionTree\DepthFirstTraceMethod;

/**
 * Decision tree dispatch policy example.
 *
 * This example builds a small policy tree for handling
 * incoming requests and demonstrates how to obtain both
 * a decision and an explanation trace using
 * DepthFirstTraceMethod with shared runtime state plus
 * an injected assessor.
 */

$eval = new Func(function (array $value): float {
    return (float)($value['score'] ?? 0.0);
});

$root = new Node([
    'id' => 'root',
    'decision' => 'evaluate_request',
    'score' => 0,
], $eval);

$evacCritical = new Node([
    'id' => 'evac_critical',
    'decision' => 'evacuation',
    'score' => 5,
], $eval);

$supplyCritical = new Node([
    'id' => 'supply_critical',
    'decision' => 'supply',
    'score' => 4,
], $eval);

$deny = new Node([
    'id' => 'deny_low_priority',
    'decision' => 'deny',
    'score' => 1,
], $eval);

$acceptGround = new Node([
    'id' => 'accept_ground',
    'decision' => 'accept_ground_dispatch',
    'score' => 7,
], $eval);

$escalateAir = new Node([
    'id' => 'escalate_air',
    'decision' => 'escalate_to_airlift',
    'score' => 9,
], $eval);

$root->addChild($evacCritical);
$root->addChild($supplyCritical);
$root->addChild($deny);

$evacCritical->addChild($acceptGround);
$evacCritical->addChild($escalateAir);

$tree = new DecisionTree();
$tree->setRoot($root);

$method = (new DepthFirstTraceMethod())
    ->setState([
        'resources' => [
            'airlift_ready' => false,
            'ground_units' => 3,
        ],
        'request' => [
            'severity' => 'critical',
            'people_at_risk' => 42,
        ],
    ])
    ->setAssessor(new Func(function (array $value, array $children, array $state): float {
        $score = (float)($value['score'] ?? 0.0);

        if (($value['decision'] ?? null) === 'escalate_to_airlift' && !($state['resources']['airlift_ready'] ?? false)) {
            $score -= 5.0;
        }

        if (($value['decision'] ?? null) === 'accept_ground_dispatch' && ($state['resources']['ground_units'] ?? 0) > 0) {
            $score += 2.0;
        }

        if (($state['request']['severity'] ?? null) === 'critical' && str_contains((string)($value['decision'] ?? ''), 'dispatch')) {
            $score += 1.0;
        }

        return $score;
    }));
$decision = $tree->decide($method);

echo "=== Dispatch Policy Decision Tree Example ===\n\n";
echo "Method state:\n";
echo "- airlift_ready: false\n";
echo "- ground_units: 3\n";
echo "- request severity: critical\n\n";

echo "Decision: " . ($decision['decision'] ?? 'unknown') . "\n\n";

echo "Trace (node ids):\n";
foreach ($method->getTrace() as $node) {
    /** @var Node $node */
    $value = $node->getValue();
    echo "- " . ($value['id'] ?? 'unknown') . " (" . ($value['decision'] ?? 'n/a') . ")\n";
}

echo "\nExample completed.\n";

