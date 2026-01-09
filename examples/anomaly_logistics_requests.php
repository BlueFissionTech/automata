<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\Analysis\KNearestExplorer;
use BlueFission\Automata\Analysis\KNearestAnomaly;

/**
 * KNN anomaly detection example (logistics requests).
 *
 * Features (for demo):
 * - distance_km
 * - road_risk (0..5)
 * - evacuee_count (scaled)
 *
 * We score how anomalous a new request is relative to
 * past requests. Higher scores imply more unusual cases
 * that might merit verification.
 */

// Synthetic historical requests.
// distance_km, road_risk, evacuee_count
$samples = [
    [5, 1, 20],
    [7, 1, 25],
    [10, 2, 30],
    [12, 2, 35],
    [15, 3, 40],
];

$ids = ['R1', 'R2', 'R3', 'R4', 'R5'];

$explorer = new KNearestExplorer($samples, $ids);
$anomaly = new KNearestAnomaly($explorer);

$normalRequest = [9, 2, 28];
$anomalousRequest = [50, 5, 5]; // very far, high risk, few evacuees

$k = 3;
$normalScore = $anomaly->score($normalRequest, $k);
$anomalousScore = $anomaly->score($anomalousRequest, $k);

$threshold = ($normalScore + $anomalousScore) / 2;

echo "=== KNN Anomaly Detection (Requests) ===\n\n";

echo "Normal-like request score: " . round($normalScore, 3) . "\n";
echo "Anomalous-like request score: " . round($anomalousScore, 3) . "\n";
echo "Threshold: " . round($threshold, 3) . "\n\n";

echo "Is normal request anomalous? " .
    ($anomaly->isAnomalous($normalRequest, $k, $threshold) ? 'yes' : 'no') . "\n";
echo "Is anomalous request anomalous? " .
    ($anomaly->isAnomalous($anomalousRequest, $k, $threshold) ? 'yes' : 'no') . "\n";

echo "\nExample completed.\n";

