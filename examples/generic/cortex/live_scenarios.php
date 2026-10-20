<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
foreach (['ScenarioAdapter', 'LiveProvider', 'LiveScenario', 'GameOracle', 'RoutingCorrection'] as $name) {
    require_once __DIR__ . '/scenarios/' . $name . '.php';
}

use BlueFission\Arr;
use CortexExample\Scenarios\LiveScenario;

// Live traffic requires explicit opt-in and stays outside default CI.
if (getenv('AUTOMATA_CORTEX_LIVE') !== '1') {
    echo json_encode(['status' => 'UNATTEMPTED', 'reason' => 'Set AUTOMATA_CORTEX_LIVE=1 to opt in.']) . PHP_EOL;
    exit(0);
}
$providers = isset($argv[1]) ? [$argv[1]] : ['openai', 'gemini', 'pokee'];
$reports = Arr::make($providers)->map(static fn ($provider) => (new LiveScenario())->run($provider))->val();
$passed = Arr::make($reports)->filter(static fn ($report) => $report['status'] !== 'PASS')->size() === 0;
echo json_encode(['experiment' => 'cortex-live-proposals-v1', 'passed' => $passed,
    'reports' => $reports], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
exit($passed ? 0 : 1);
