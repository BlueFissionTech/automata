<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/SensoryCapture.php';

use BlueFission\Str;
use BlueFission\Arr;
use BlueFission\Automata\Learning\{CallbackTrainingAdapter, Experience, ExperienceRecomposer, InMemoryExperienceStore, Outcome, TrainingExample};
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use BlueFission\Examples\Cortex\SensoryCapture;

// This is the ingress companion to run.php. Every training and evaluation text
// passes through Input -> Sense before explicit projection to an Experience.
['training' => $training, 'holdout' => $holdout] = require __DIR__ . '/fixtures.php';
$capture = new SensoryCapture();
$store = new InMemoryExperienceStore();
$unlabelled = [];
foreach ($training as $i => [$text, $label]) {
    // Deliberately vary presentation while preserving the frozen corpus content.
    $raw = "  " . Str::make($text)->replace(' ', "\t")->upper()->val() . "  ";
    $experience = $capture->capture('sensory-train-' . $i, $raw, 'synthetic-training', 'sensory-trace-' . $i);
    $unlabelled[] = $experience;
    // Host-selected fixture annotations are separate from sensory measurements.
    // Neither repetition nor attention is allowed to invent a training label.
    $store->save($experience->withOutcome(new Outcome('sensory-label-' . $i,
        $experience->id(), 'fixture-annotation', true, ['intent' => $label])));
}
$pending = $capture->capture('sensory-pending', 'do NOT book 0', 'synthetic-pending', 'pending-trace');
$store->save($pending);

// Projection admits only this fixture's successful, known annotation labels.
// Source strings are not authentication; the host supplies these trusted objects.
$adapter = new CallbackTrainingAdapter('cortex.sensory-intent', '1', static function (Experience $experience): iterable {
    return Arr::make($experience->outcomes())
        ->filter(static fn (Outcome $outcome): bool => $outcome->successful()
            && $outcome->toArray()['source'] === 'fixture-annotation'
            && Arr::make(['directions', 'luggage', 'checkin'])->has($outcome->observations()['intent'] ?? null, true))
        ->map(static fn (Outcome $outcome): TrainingExample => new TrainingExample(
            $experience->id(), $outcome->id(), $experience->toArray()['context']['data']['utterance'],
            $outcome->observations()['intent']))->values()->val();
});
$recomposer = new ExperienceRecomposer();
$beforeLabels = $recomposer->compose($unlabelled, $adapter);
$batch = $recomposer->compose($store->experiences(), $adapter);

// Train an isolated real classifier on all 12 projected examples. No model is
// activated, no provider is called, and no operational action is executed.
$candidate = new NaiveBayesTextClassification();
$candidate->getPipeline()->train($batch->samples(), $batch->labels());
$predictions = [];
$correct = $constantCorrect = 0;
$disjoint = true;
foreach ($holdout as $i => [$text, $expected]) {
    $observation = $capture->capture('sensory-eval-' . $i, $text, 'synthetic-evaluation', 'eval-trace-' . $i);
    $sample = $observation->toArray()['context']['data']['utterance'];
    $disjoint = $disjoint && !Arr::make($batch->samples())->has($sample, true);
    $predicted = $candidate->predict($sample);
    $correct += (int)($predicted === $expected);
    $constantCorrect += (int)($expected === 'directions');
    $predictions[] = ['input' => $sample, 'expected' => $expected, 'predicted' => $predicted];
}

// Probe sparse input, repetition, falsey text, state isolation and failure paths
// independently of the classifier's tiny synthetic accuracy result.
$zero = $capture->capture('zero', '0', 'fixture', 'zero-trace')->toArray();
$repeated = $capture->capture('repeat', 'help help help', 'fixture', 'repeat-trace')->toArray();
$first = $unlabelled[0]->toArray();
$again = $capture->capture('again', $first['context']['data']['raw_text'], 'synthetic-training', 'again-trace')->toArray();
$detached = $first;
$detached['context']['data']['utterance'] = 'changed';
$rejected = 0;
foreach ([null, " \t", str_repeat('x', 257), str_repeat('word ', 33)] as $invalid) {
    try { $capture->capture('invalid', $invalid, 'fixture', 'invalid-trace'); }
    catch (InvalidArgumentException) { ++$rejected; }
}
$sensory = $first['context']['data']['sensory'];
$pendingData = $pending->toArray()['context']['data'];
$expectedSamples = Arr::make($training)->map(static fn (array $row) => $row[0])->values()->val();
$matchesProjection = static fn (array $samples): bool => $samples === $expectedSamples;
$damagedSamples = $batch->samples();
$damagedSamples[0] = '';
$restored = new Experience(json_decode(json_encode($unlabelled[0],
    JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR));
$checks = [
    'input_normalization_matches_frozen_samples' => $matchesProjection($batch->samples()),
    'real_sense_preserves_first_sweep_chunks' => Arr::make($sensory['chunks'])->map(static fn (array $row) => $row['text'])->values()->val() === Str::make($training[0][0])->split(' ')->val(),
    'sense_completion_is_observed' => $sensory['sweeps'] > 0 && $sensory['sweeps'] === $sensory['completion_events'],
    'recursion_is_visible_and_bounded_in_fixture' => $sensory['depth'] <= 7 && $sensory['sweeps'] <= 8,
    'raw_input_and_digest_retained' => $first['provenance']['raw_sha256'] === hash('sha256', $first['context']['data']['raw_text']),
    'negation_and_zero_preserved' => $pendingData['utterance'] === 'do not book 0' && $zero['context']['data']['utterance'] === '0',
    'repetition_changes_inspection_hint' => $repeated['context']['data']['sensory']['inspection_hint'] === 'inspect'
        && $sensory['inspection_hint'] === 'standard',
    'sensory_measurements_are_not_confidence' => $sensory['confidence'] === null,
    'successive_observations_are_isolated' => $again['context']['data'] === $first['context']['data'],
    'snapshot_is_detached' => $unlabelled[0]->toArray() === $first,
    'snapshot_round_trip' => $restored->toArray() === $first,
    'unsupported_and_oversized_inputs_rejected' => $rejected === 4,
    'no_training_examples_without_labels' => $beforeLabels->samples() === [] && $pending->outcomes() === [],
    'only_labelled_observations_projected' => Arr::make($batch->samples())->count() === 12 && !Arr::make($batch->samples())->has('do not book 0', true),
    'holdout_not_in_training' => $disjoint,
    'all_held_out_predictions_correct' => $correct === Arr::make($holdout)->count(),
    'constant_prediction_control_loses' => $correct > $constantCorrect,
    'lost_content_control_rejected' => !$matchesProjection($damagedSamples),
];
$passed = !Arr::make($checks)->has(false, true);
echo json_encode([
    'experiment' => 'cortex-sensory-ingestion-v1', 'passed' => $passed, 'checks' => $checks,
    'example_observation' => $first,
    'inspection_example' => $repeated['context']['data'],
    'training_examples' => Arr::make($batch->samples())->count(), 'evaluation_examples' => Arr::make($holdout)->count(),
    'correct_predictions' => $correct, 'constant_correct_predictions' => $constantCorrect,
    'predictions' => $predictions, 'training_lineage' => $batch->toArray(),
    'limits' => ['Bounded ASCII text and synthetic labels only; no multimodal decoding.',
        'Custom preparation and first-sweep capture accommodate legacy Sense limitations.',
        'CRC32 grouping may collide; attention is heuristic, not confidence, permission or a hard budget.',
        'Inspection hints are advisory; the preserved text, host admission and external labels remain separate.',
        'No InputArray queue, durable recovery, production qualification or model activation.'],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
