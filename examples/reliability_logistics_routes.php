<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\Analysis\BetaReliability;

/**
 * BetaReliability example (route/asset reliability).
 *
 * We track success/failure histories for a couple of
 * edges and assets and print posterior mean reliabilities.
 */

$reliability = new BetaReliability();

// Edge observations (success = route open/passable).
// edge:Bridge-1 has 3 successes, 2 failures.
$reliability->update('edge:Bridge-1', true);
$reliability->update('edge:Bridge-1', true);
$reliability->update('edge:Bridge-1', true);
$reliability->update('edge:Bridge-1', false);
$reliability->update('edge:Bridge-1', false);

// edge:Highway-Loop has 5 successes, 0 failures.
for ($i = 0; $i < 5; $i++) {
    $reliability->update('edge:Highway-Loop', true);
}

// Asset observations.
// asset:Truck-A: 4 successes, 1 failure.
for ($i = 0; $i < 4; $i++) {
    $reliability->update('asset:Truck-A', true);
}
$reliability->update('asset:Truck-A', false);

// asset:Boat-1: 1 success, 3 failures.
$reliability->update('asset:Boat-1', true);
for ($i = 0; $i < 3; $i++) {
    $reliability->update('asset:Boat-1', false);
}

echo "=== Beta Reliability Example ===\n\n";

foreach (['edge:Bridge-1', 'edge:Highway-Loop', 'asset:Truck-A', 'asset:Boat-1'] as $key) {
    $params = $reliability->parameters($key);
    $mean = $reliability->mean($key);
    echo "{$key} -> alpha={$params['alpha']}, beta={$params['beta']}, mean=" . round($mean, 3) . "\n";
}

echo "\nExample completed.\n";

