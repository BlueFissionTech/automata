<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Learning\ClassificationEvaluator;
use BlueFission\Automata\Learning\ModelCandidate;
use BlueFission\Automata\Learning\TrainingBatch;
use BlueFission\Automata\Learning\TrainingExample;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;

// Separate, independently labelled synthetic corpora; no provider or operational effects.
['training' => $trainingRows, 'holdout' => $holdoutRows] = require __DIR__ . '/fixtures.php';
$batch = static fn (array $rows, string $split): TrainingBatch => new TrainingBatch('concierge.intent', '1',
    Arr::make($rows)->map(static fn (array $row, int $index): TrainingExample => new TrainingExample(
        $split . '-' . $index, $split . '-label-' . $index, $row[0], $row[1]))->values()->val());
$training = $batch($trainingRows, 'training');
$holdout = $batch($holdoutRows, 'holdout');
$constant = static fn (): IStrategy => new class implements IStrategy {
    public function predict($input) { return 'directions'; }
    public function train(array $samples, array $labels, float $testSize = 0.2) { throw new LogicException('Fixed prior.'); }
    public function accuracy(): float { throw new LogicException('Measure held-out accuracy.'); }
    public function saveModel(string $path): bool { return false; }
    public function loadModel(string $path): bool { return false; }
};
$trained = new NaiveBayesTextClassification();
$trained->getPipeline()->train($training->samples(), $training->labels());
$prior = new ModelCandidate('concierge.intent', 'constant-1', $constant(), $batch([], 'prior'));
$candidate = new ModelCandidate('concierge.intent', 'bayes-1', $trained, $training);
$worse = new ModelCandidate('concierge.intent', 'constant-2', $constant(), $batch([], 'worse'));
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
