<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\Statement;
use BlueFission\Automata\Learning\CallbackTrainingAdapter;
use BlueFission\Automata\Learning\Experience;
use BlueFission\Automata\Learning\ExperienceRecomposer;
use BlueFission\Automata\Learning\InMemoryExperienceStore;
use BlueFission\Automata\Learning\Outcome;
use BlueFission\Automata\Learning\TrainingExample;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;

// Synthetic, independently labelled fixtures. No model is asked to grade itself.
$training = [
    ['breakfast restaurant directions', 'directions'],
    ['where breakfast restaurant', 'directions'],
    ['find elevator directions', 'directions'],
    ['where elevator location', 'directions'],
    ['transport luggage bags', 'luggage'],
    ['bring luggage upstairs', 'luggage'],
    ['carry bags upstairs', 'luggage'],
    ['luggage delivery transport', 'luggage'],
    ['reservation checkin arrival', 'checkin'],
    ['confirm reservation booking', 'checkin'],
    ['checkin booking room', 'checkin'],
    ['reservation room arrival', 'checkin'],
];
$holdout = [
    ['breakfast location', 'directions'],
    ['find elevator', 'directions'],
    ['transport bags upstairs', 'luggage'],
    ['bring luggage', 'luggage'],
    ['confirm booking', 'checkin'],
    ['reservation arrival', 'checkin'],
];
$store = new InMemoryExperienceStore();
$memory = new Holoscene('concierge-training-fixtures');
foreach ($training as $index => [$text, $intent]) {
    $id = 'concierge-' . $index;
    $statement = new Statement();
    $statement->assign(['subject' => 'guest', 'behavior' => 'requests', 'object' => $text]);
    $experience = Experience::fromStatements($id, [$statement], new Context(['utterance' => $text]), [
        'timestamp' => '2026-09-19T12:00:00Z',
        'trace_id' => 'trace-' . $id,
        'provenance' => ['dataset' => 'synthetic-concierge-v1', 'split' => 'training'],
    ])->withOutcome(new Outcome('label-' . $index, $id, 'fixture-annotation', true, ['intent' => $intent]));
    $store->save($experience);
    $memory->push($id, $experience);
}
$store->save(Experience::fromStatements('pending-review', [], new Context(['utterance' => 'unknown request'])));
$memory->review();
$adapter = new CallbackTrainingAdapter('concierge.intent', '1', static function (Experience $experience): iterable {
    foreach ($experience->outcomes() as $outcome) {
        $intent = $outcome->observations()['intent'] ?? null;
        if ($outcome->successful() && Str::is($intent)) {
            yield new TrainingExample($experience->id(), $outcome->id(),
                $experience->toArray()['context']['data']['utterance'], $intent);
        }
    }
});
$batch = (new ExperienceRecomposer())->compose($store->experiences(), $adapter);
$candidate = new NaiveBayesTextClassification();
// Train all projected examples through the public pipeline. The strategy's train()
// method performs its own random split; this experiment uses separate holdouts.
$candidate->getPipeline()->train($batch->samples(), $batch->labels());
$baselineCorrect = 0;
$candidateCorrect = 0;
$predictions = [];
foreach ($holdout as [$text, $expected]) {
    if (Arr::has($batch->samples(), $text, true)) {
        throw new RuntimeException('Evaluation input leaked into the training corpus.');
    }
    $predicted = $candidate->predict($text);
    $baselineCorrect += (int) ($expected === 'directions');
    $candidateCorrect += (int) ($expected === $predicted);
    $predictions[] = ['input' => $text, 'expected' => $expected, 'predicted' => $predicted];
}
$first = $store->get('concierge-0');
$restored = new Experience(json_decode(json_encode($first,
    JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR));
$checks = [
    'all_observed_examples_projected' => Arr::count($batch->samples()) === Arr::count($training),
    'pending_experience_excluded' => !Arr::has($batch->samples(), 'unknown request', true),
    'episodic_snapshots_recorded' => Arr::count($memory->assessment()) === Arr::count($training),
    'snapshot_round_trip' => $restored->toArray() === $first->toArray(),
    'candidate_improves_over_constant_prior' => $candidateCorrect > $baselineCorrect,
    'all_held_out_predictions_correct' => $candidateCorrect === Arr::count($holdout),
];
$passed = !Arr::has($checks, false, true);
echo json_encode([
    'experiment' => 'cortex-experiential-foundation-v1',
    'fixture_kind' => 'synthetic',
    'passed' => $passed,
    'checks' => $checks,
    'training_examples' => Arr::count($batch->samples()),
    'evaluation_examples' => Arr::count($holdout),
    'constant_prior_accuracy' => Num::divide($baselineCorrect, Arr::count($holdout)),
    'candidate_accuracy' => Num::divide($candidateCorrect, Arr::count($holdout)),
    'predictions' => $predictions,
    'training_lineage' => $batch->toArray(),
    'limits' => ['No live provider or physical actions.',
        'Candidate promotion, route feedback, response scheduling and durable recovery are subsequent slices.'],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
