<?php

use BlueFission\Arr;
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
return ['prior' => $prior, 'candidate' => $candidate, 'worse' => $worse, 'holdout' => $holdout];
