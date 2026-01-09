<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\Node;
use BlueFission\Automata\DecisionTree\DepthFirstTraceMethod;

/**
 * Decision tree dispatch policy example.
 *
 * This example builds a small policy tree for handling
 * incoming requests and demonstrates how to obtain both
 * a decision and an explanation trace using
 * DepthFirstTraceMethod.
 */

$eval = function (array $value, array $children): float {
    return (float)($value['score'] ?? 0.0);
};

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

$method = new DepthFirstTraceMethod();
$decision = $tree->decide($method);

echo "=== Dispatch Policy Decision Tree Example ===\n\n";

echo "Decision: " . ($decision['decision'] ?? 'unknown') . "\n\n";

echo "Trace (node ids):\n";
foreach ($method->getTrace() as $node) {
    /** @var Node $node */
    $value = $node->getValue();
    echo "- " . ($value['id'] ?? 'unknown') . " (" . ($value['decision'] ?? 'n/a') . ")\n";
}

echo "\nExample completed.\n";

