<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
foreach (['ScenarioAdapter', 'GameOracle', 'RoutingCorrection'] as $name) {
    require_once __DIR__ . '/scenarios/' . $name . '.php';
}

use BlueFission\Arr;
use CortexExample\Scenarios\RoutingCorrection;

// Frozen, sanitized observations preserve actual failures without requiring
// keys or more provider spend. Re-evaluate proposals; never trust stored labels.
$fixtures = json_decode(file_get_contents(__DIR__ . '/scenarios/observed-game-proposals.json'),
    true, 32, JSON_THROW_ON_ERROR);
$reports = Arr::make($fixtures)->map(static function (array $fixture): array {
    $observations = [];
    foreach ($fixture['observations'] as $row) { $observations[$row['remaining']] = $row['proposal']; }
    return ['provider' => $fixture['provider'], 'observed_on' => $fixture['observed_on'],
        'correction' => (new RoutingCorrection())->run($observations)];
})->val();
$passed = Arr::make($reports)->filter(static fn ($row) => $row['correction']['status'] !== 'PASS')->size() === 0;
echo json_encode(['experiment' => 'recorded-provider-routing-v1', 'passed' => $passed,
    'reports' => $reports], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
exit($passed ? 0 : 1);
