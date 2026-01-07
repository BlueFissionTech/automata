<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\Strategy\KNearestRegression;
use BlueFission\Automata\Analysis\KNearestExplorer;
use BlueFission\Automata\Analysis\BetaReliability;

/**
 * KNN ETA prediction example (logistics).
 *
 * Features (for demo):
 * - distance_km
 * - road_risk (0..5)
 * - asset_speed_kmph
 *
 * Target:
 * - eta_minutes
 *
 * We also compute a simple reliability estimate for an
 * asset based on observed success/failures.
 */

// Synthetic historical missions.
$samples = [
    // distance, risk, speed
    [10, 1, 60],
    [15, 2, 60],
    [20, 3, 50],
    [25, 4, 40],
    [30, 1, 80],
];

$labels = [
    12, // minutes
    20,
    35,
    50,
    25,
];

// IDs for explanation.
$missionIds = ['M1', 'M2', 'M3', 'M4', 'M5'];

$reg = new KNearestRegression(3);
$reg->train($samples, $labels, 0.0);

$explorer = new KNearestExplorer($samples, $missionIds);

$reliability = new BetaReliability();
// Pretend we observed some successes/failures for asset "Truck-A".
$reliability->update('asset:Truck-A', true);
$reliability->update('asset:Truck-A', true);
$reliability->update('asset:Truck-A', false);

// New request.
$requestFeatures = [18, 2, 55];

$predEta = $reg->predict($requestFeatures);
$neighbors = $explorer->neighbors($requestFeatures, 3);
$assetReliability = $reliability->mean('asset:Truck-A');

echo "=== KNN ETA Prediction Example ===\n\n";
echo "Predicted ETA (minutes): " . round($predEta, 2) . "\n";
echo "Asset Truck-A reliability (mean): " . round($assetReliability, 3) . "\n\n";

echo "Nearest neighbors:\n";
foreach ($neighbors as $n) {
    $id = $n['id'];
    $idx = $n['index'];
    $dist = $n['distance'];
    $eta = $labels[$idx];
    echo "- {$id}: distance=" . round($dist, 3) . ", eta_minutes={$eta}\n";
}

echo "\nExample completed.\n";

