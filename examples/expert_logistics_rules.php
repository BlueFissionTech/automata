<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\Expert\Expert;
use BlueFission\Automata\Expert\Fact;
use BlueFission\Automata\Expert\Rule;
use BlueFission\Automata\Expert\ForwardChainingReasoner;
use BlueFission\Automata\Expert\Approach;
use BlueFission\Automata\Expert\DepthFirstMethod;

/**
 * Expert system example (disaster logistics rules).
 *
 * Facts:
 * - road_open (bool)
 * - hospital_needs_supplies (bool)
 *
 * Rules (generic structure, domain-specific content):
 * - If hospital_needs_supplies AND road_open => dispatch_ground
 * - If hospital_needs_supplies AND NOT road_open => dispatch_air
 */

$expert = new Expert();

// Base facts for this scenario.
$expert->addFact(new Fact('hospital_needs_supplies', true));
$expert->addFact(new Fact('road_open', false));

// Dispatch decisions we want the expert to infer.
$dispatchGround = new Fact('dispatch_ground', true);
$dispatchAir = new Fact('dispatch_air', true);

$ruleGround = new Rule(
    'if_needs_and_road_open_then_ground',
    function ($facts) {
        return isset($facts['hospital_needs_supplies'], $facts['road_open'])
            && $facts['hospital_needs_supplies']->evaluate()
            && $facts['road_open']->evaluate();
    },
    $dispatchGround
);

$ruleAir = new Rule(
    'if_needs_and_not_road_open_then_air',
    function ($facts) {
        return isset($facts['hospital_needs_supplies'], $facts['road_open'])
            && $facts['hospital_needs_supplies']->evaluate()
            && !$facts['road_open']->evaluate();
    },
    $dispatchAir
);

$expert->addRule($ruleGround);
$expert->addRule($ruleAir);

// Use a forward-chaining reasoner via Approach.
$reasoner = new ForwardChainingReasoner(new DepthFirstMethod());
$approach = new Approach($reasoner);
$expert->setStrategy($approach);
$expert->reason();

echo "=== Expert System Logistics Rules Example ===\n\n";

$facts = $expert->getFacts();
foreach ($facts as $name => $fact) {
    /** @var Fact $fact */
    echo "- {$name} = " . ($fact->evaluate() ? 'true' : 'false') . "\n";
}

echo "\nInferred decisions:\n";
echo "dispatch_ground present? " . ($expert->query(new Fact('dispatch_ground', true)) ? 'yes' : 'no') . "\n";
echo "dispatch_air present? " . ($expert->query(new Fact('dispatch_air', true)) ? 'yes' : 'no') . "\n";

echo "\nExample completed.\n";

