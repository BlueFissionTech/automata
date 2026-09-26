<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Learning\ClassificationEvaluator;

['prior' => $prior, 'candidate' => $candidate, 'worse' => $worse, 'holdout' => $holdout]
    = require __DIR__ . '/models.php';
$trained = $candidate->strategy();
$evaluator = new ClassificationEvaluator(minimumSamples: 6, minimumAccuracy: 0.8);
$improvement = $evaluator->compare($prior, $candidate, $holdout);
$regression = $evaluator->compare($candidate, $worse, $holdout);
$checks = [
    'measured_improvement_recommended' => $improvement['recommended'],
    'worse_candidate_rejected' => !$regression['recommended'],
    'all_held_out_predictions_correct' => $improvement['candidate']['accuracy'] === 1.0,
    'incumbent_retained_after_rejection' => $candidate->strategy() === $trained
        && $trained->predict('confirm booking') === 'checkin',
    'unknown_cost_preserved' => $improvement['candidate']['cost'] === null,
];
$passed = !Arr::has($checks, false, true);
echo json_encode([
    'experiment' => 'cortex-classification-evaluation-v1', 'fixture_kind' => 'synthetic',
    'passed' => $passed, 'checks' => $checks,
    'improvement' => $improvement, 'regression' => $regression,
    'limits' => ['Recommendations do not promote models or grant execution authority.',
        'Declared training lineage cannot reveal undisclosed pretraining or semantic duplicates.',
        'Prediction calls are synchronous; sample and measured latency limits are not cancellation or spend limits.',
        'No statistical significance or production quality claim follows from these small fixtures.'],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
