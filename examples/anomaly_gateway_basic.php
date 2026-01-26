<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\Anomaly\Activity;
use BlueFission\Automata\Anomaly\Gateway;
use BlueFission\Automata\Anomaly\Strategies\KMeansDetector;
use BlueFission\Automata\Anomaly\Strategies\KNearestDetector;
use BlueFission\Automata\Context;

/**
 * Basic anomaly gateway example.
 *
 * Demonstrates running multiple detectors over a single activity
 * and surfacing scored indicators + anomaly flags.
 */

$samples = [
    [1.0, 1.0],
    [1.1, 1.0],
    [0.9, 1.2],
    [2.0, 1.8],
    [10.0, 10.0],
];

$knn = new KNearestDetector(2, 3.0);
$knn->train($samples, []);

$kmeans = new KMeansDetector(2);
$kmeans->train($samples, []);

$gateway = new Gateway();
$gateway->registerDetector($knn, 'knn', ['threshold' => 3.0]);
$gateway->registerDetector($kmeans, 'kmeans', ['threshold' => 0.7]);

$context = new Context();
$context->set('device', 'new');
$context->addTag('location:remote', 0.8);

$activity = new Activity([
    'id' => 'txn-001',
    'features' => [9.5, 9.5],
    'tags' => ['device:new', 'location:remote'],
    'context' => $context,
]);

$result = $gateway->analyze($activity);

echo "=== Anomaly Gateway (basic) ===\n\n";
echo "Scores:\n";
foreach ($result->top(5) as $indicator) {
    $label = $indicator['label'] ?? '';
    $score = $indicator['score'] ?? 0.0;
    $flagged = !empty($indicator['meta']['flagged']) ? 'yes' : 'no';
    echo "- {$label}: score=" . round((float)$score, 3) . " flagged={$flagged}\n";
}

$anomalies = $result->anomalies();
echo "\nAnomalies flagged: " . count($anomalies) . "\n";
