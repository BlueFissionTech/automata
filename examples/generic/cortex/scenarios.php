<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
foreach (['ScenarioAdapter', 'ScenarioRuntime', 'GameOracle', 'GameScenario', 'ConversationScenario',
    'EffectHost', 'OperationsScenario', 'NpcScenario', 'ContinualLearningScenario'] as $name) {
    require_once __DIR__ . '/scenarios/' . $name . '.php';
}

use BlueFission\Arr;
use CortexExample\Scenarios\{ConversationScenario, GameScenario, OperationsScenario, NpcScenario, ContinualLearningScenario};

// Always emit the actual experiment report, including independently replayable
// transcripts. No network calls or credentials are required by this entrypoint.
$reports = Arr::make([new ConversationScenario(), new OperationsScenario(), new GameScenario(), new NpcScenario(), new ContinualLearningScenario()])
    ->map(static fn ($scenario) => $scenario->run())->val();
$failed = Arr::make($reports)->filter(static fn ($report) => $report['status'] !== 'PASS')->size();
echo json_encode(['experiment' => 'cortex-agent-outcomes-v1', 'passed' => $failed === 0,
    'reports' => $reports], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
