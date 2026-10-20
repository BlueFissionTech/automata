<?php

namespace CortexExample\Scenarios;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Learning\{CallbackTrainingAdapter, ClassificationEvaluator, Experience, ExperienceRecomposer, InMemoryExperienceStore, LearningCoordinator, ModelCandidate, ModelLifecycle, Outcome, TrainingBatch, TrainingExample, TrainingPolicy, TrainingTrigger};
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Strategy\{IStrategy, NaiveBayesTextClassification};

/** Two observed correction batches must change future predictions, not history. */
final class ContinualLearningScenario
{
    /** Freeze maintenance observations and evaluation utterances before fitting. */
    public const NEW_OBSERVATIONS = [
        ['repair leaking faucet', 'maintenance'], ['broken heater repair', 'maintenance'],
        ['fix leaking pipe', 'maintenance'], ['heater broken maintenance', 'maintenance'],
    ];
    public const NEW_HOLDOUT = [['repair heater', 'maintenance'], ['leaking faucet broken', 'maintenance']];

    /** Train isolated candidates, evaluate held-out cases and approve separately. */
    public function run(): array
    {
        ['training' => $rows, 'holdout' => $heldout] = require __DIR__ . '/../fixtures.php';
        $initial = new NaiveBayesTextClassification();
        $initial->getPipeline()->train(['directions'], ['directions']);
        $prior = new ModelCandidate('conversation.intent', 'initial', $initial,
            new TrainingBatch('conversation.intent', '1', []));
        $coordinator = new LearningCoordinator($prior, new TrainingPolicy(minimumExamples: 6, minimumNewExamples: 4));
        $lifecycle = new ModelLifecycle($prior, new ClassificationEvaluator(minimumSamples: 6));
        $store = new InMemoryExperienceStore();
        $adapter = new CallbackTrainingAdapter('conversation.intent', '1', static fn (Experience $experience): iterable =>
            Arr::make($experience->outcomes())->map(static fn (Outcome $outcome): TrainingExample =>
                new TrainingExample($experience->id(), $outcome->id(),
                    $experience->toArray()['context']['data']['utterance'], $outcome->observations()['label']))->val());
        $trainCalls = 0;
        $factory = static fn (): IStrategy => new NaiveBayesTextClassification();
        $train = static function (IStrategy $model, TrainingBatch $batch) use (&$trainCalls): void {
            ++$trainCalls;
            $model->getPipeline()->train($batch->samples(), $batch->labels());
        };
        $approve = static fn () => GovernanceDecision::approved('Synthetic bounded training fixture.');
        $append = static function (array $observations, string $prefix) use ($store): void {
            foreach ($observations as $i => [$utterance, $label]) {
                $id = $prefix . '-' . $i;
                $store->save(Experience::fromStatements($id, [], new Context(['utterance' => $utterance]))
                    ->withOutcome(new Outcome($id . '-label', $id, 'synthetic-user-correction', true, ['label' => $label])));
            }
        };
        $holdout = static fn (array $examples): TrainingBatch => new TrainingBatch('conversation.intent', '1',
            Arr::make($examples)->map(static fn ($row, $i): TrainingExample =>
                new TrainingExample('evaluation-' . $i, 'evaluation-label-' . $i, $row[0], $row[1]))->val());

        $append($rows, 'first');
        $firstBatch = (new ExperienceRecomposer())->compose($store->experiences(), $adapter);
        $first = $coordinator->train('first-training', 'bayes-1', $firstBatch, new TrainingTrigger(), $factory, $train, $approve);
        $activation1 = $lifecycle->promote('first-activation', 0, $first->candidate(), $holdout($heldout), $approve);
        $frozen = $lifecycle->active();
        $before = Arr::make(self::NEW_HOLDOUT)->map(static fn ($row) => $frozen->strategy()->predict($row[0]))->val();

        // Three new corrections do not meet the policy's four-new-example gate.
        $append(Arr::make(self::NEW_OBSERVATIONS)->slice(0, 3)->val(), 'second');
        $partial = (new ExperienceRecomposer())->compose($store->experiences(), $adapter);
        $deferred = $coordinator->train('partial-training', 'bayes-pending', $partial, new TrainingTrigger(), $factory, $train, $approve);
        $callsBeforeFourth = $trainCalls;
        $append([self::NEW_OBSERVATIONS[3]], 'fourth');
        $secondBatch = (new ExperienceRecomposer())->compose($store->experiences(), $adapter);
        $second = $coordinator->train('second-training', 'bayes-2', $secondBatch, new TrainingTrigger(), $factory, $train, $approve);
        $notAutomaticallyActive = $lifecycle->active() === $frozen;
        $allHeldout = $holdout([...$heldout, ...self::NEW_HOLDOUT]);
        $denied = $lifecycle->promote('denied-activation', 1, $second->candidate(), $allHeldout,
            static fn () => GovernanceDecision::denied());
        $activation2 = $lifecycle->promote('second-activation', 1, $second->candidate(), $allHeldout, $approve);
        $after = Arr::make(self::NEW_HOLDOUT)->map(static fn ($row) => $lifecycle->active()->strategy()->predict($row[0]))->val();
        $replayed = $coordinator->train('second-training', 'bayes-2', $secondBatch, new TrainingTrigger(), $factory, $train, $approve);
        $checks = [
            'first_batch_earns_activation' => $activation1['applied'],
            'partial_corrections_defer_training' => $deferred->status() === 'deferred' && $callsBeforeFourth === 1,
            'candidate_does_not_activate_itself' => $notAutomaticallyActive && !$denied['applied'],
            'new_corrections_improve_heldout_predictions' => $before !== ['maintenance', 'maintenance']
                && $after === ['maintenance', 'maintenance'],
            'second_batch_earns_activation' => $activation2['applied'],
            'previous_intents_preserved' => $activation2['evaluation']['candidate']['accuracy'] === 1.0,
            'training_replay_does_not_retrain' => $replayed === $second && $trainCalls === 2,
            'frozen_model_not_mutated' => $before === Arr::make(self::NEW_HOLDOUT)
                ->map(static fn ($row) => $frozen->strategy()->predict($row[0]))->val(),
        ];
        return ['scenario' => 'continual-learning-v1', 'status' => Arr::has($checks, false, true) ? 'FAIL' : 'PASS',
            'checks' => $checks, 'before' => $before, 'after' => $after,
            'first_activation' => $activation1, 'second_activation' => $activation2,
            'scope' => 'Two synthetic correction batches and disjoint holdout; process-local, separately approved model activation.'];
    }
}
