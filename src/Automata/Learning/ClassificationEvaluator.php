<?php

namespace BlueFission\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Num;
use BlueFission\Str;
use InvalidArgumentException;
use Throwable;

/** Measures already trained, side-effect-free classifiers; never installs a model. */
final class ClassificationEvaluator
{
    public function __construct(
        private readonly int $minimumSamples = 5,
        private readonly float $minimumAccuracy = 0.8,
        private readonly float $minimumImprovement = 0.0,
        private readonly ?float $maximumMeanLatencyMs = null,
        private readonly int $maximumSamples = 1000
    ) {
        if ($minimumSamples < 1 || $maximumSamples < $minimumSamples) {
            throw new InvalidArgumentException('Sample limits must be positive and ordered.');
        }
        foreach ([$minimumAccuracy, $minimumImprovement] as $ratio) {
            if (!Num::check($ratio, 'is_finite') || $ratio < 0.0 || $ratio > 1.0) {
                throw new InvalidArgumentException('Accuracy and improvement limits must be finite ratios.');
            }
        }
        if ($maximumMeanLatencyMs !== null
            && (!Num::check($maximumMeanLatencyMs, 'is_finite') || $maximumMeanLatencyMs < 0.0)) {
            throw new InvalidArgumentException('Latency limit must be finite and nonnegative.');
        }
    }

    public function compare(ModelCandidate $incumbent, ModelCandidate $candidate, TrainingBatch $holdout): array
    {
        if ($incumbent->strategy() === $candidate->strategy()) {
            throw new InvalidArgumentException('Incumbent and candidate must use separate strategy instances.');
        }
        if ($incumbent->identity() === $candidate->identity()) {
            throw new InvalidArgumentException('Incumbent and candidate must have distinct exact identities.');
        }
        $evaluation = $holdout->toArray();
        $rows = $evaluation['examples'];
        $count = Arr::count($rows);
        if ($count > $this->maximumSamples) {
            throw new InvalidArgumentException('Evaluation exceeds the configured sample limit.');
        }
        $trainingKeys = [];
        foreach ([$incumbent, $candidate] as $model) {
            $training = $model->training()->toArray();
            if ($training['projection_id'] !== $evaluation['projection_id']
                || $training['projection_version'] !== $evaluation['projection_version']) {
                throw new InvalidArgumentException('Training and evaluation projections must match exactly.');
            }
            foreach ($training['examples'] as $row) {
                foreach ($this->evidenceKeys($row) as $key) { $trainingKeys[$key] = true; }
            }
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!$this->isLabel($row['label'])) {
                throw new InvalidArgumentException('Classification labels must be finite non-null scalars.');
            }
            foreach ($this->evidenceKeys($row) as $key) {
                if (isset($trainingKeys[$key])) {
                    throw new InvalidArgumentException('Evaluation evidence overlaps declared training data.');
                }
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException('Duplicate holdout experience, outcome or exact sample.');
                }
                $seen[$key] = true;
            }
        }

        // All structural checks precede either model invocation.
        $baseline = $this->measure($incumbent, $rows);
        $challenger = $this->measure($candidate, $rows);
        $improvement = $count > 0
            ? (float) Num::make($challenger['accuracy'])->subtract($baseline['accuracy'])->val()
            : null;
        $reasons = [];
        if ($count < $this->minimumSamples) { $reasons[] = 'insufficient_samples'; }
        if ($baseline['failures'] > 0 || $challenger['failures'] > 0) { $reasons[] = 'prediction_failure'; }
        if ($challenger['accuracy'] === null || $challenger['accuracy'] < $this->minimumAccuracy) {
            $reasons[] = 'accuracy_below_minimum';
        }
        if ($improvement === null || $improvement <= 0.0) { $reasons[] = 'no_strict_improvement'; }
        if ($improvement !== null && $improvement < $this->minimumImprovement) {
            $reasons[] = 'improvement_below_minimum';
        }
        if ($this->maximumMeanLatencyMs !== null && $challenger['mean_latency_ms'] !== null
            && $challenger['mean_latency_ms'] > $this->maximumMeanLatencyMs) {
            $reasons[] = 'latency_limit_exceeded';
        }

        return [
            'schema_version' => 1,
            'projection_id' => $evaluation['projection_id'],
            'projection_version' => $evaluation['projection_version'],
            'policy' => [
                'minimum_samples' => $this->minimumSamples, 'maximum_samples' => $this->maximumSamples,
                'minimum_accuracy' => $this->minimumAccuracy, 'minimum_improvement' => $this->minimumImprovement,
                'maximum_mean_latency_ms' => $this->maximumMeanLatencyMs,
            ],
            'recommended' => Arr::isEmpty($reasons), 'reasons' => $reasons,
            'accuracy_improvement' => $improvement,
            'incumbent' => $baseline, 'candidate' => $challenger,
        ];
    }

    private function measure(ModelCandidate $model, array $rows): array
    {
        $correct = 0;
        $failures = 0;
        $elapsed = 0.0;
        $predictions = [];
        foreach ($rows as $row) {
            $prediction = null;
            $error = null;
            $started = hrtime(true);
            try {
                $prediction = $model->strategy()->predict($row['sample']);
            } catch (Throwable $failure) {
                // Class names are useful diagnostics; exception messages may contain private input.
                $error = ['code' => 'prediction_exception', 'type' => $failure::class];
            }
            $duration = (float) Num::make(hrtime(true))->subtract($started)->divide(1000000)->val();
            if ($error === null) {
                try {
                    $prediction = RecordSnapshot::copy($prediction);
                    if (!$this->isLabel($prediction)) {
                        throw new InvalidArgumentException('Prediction is not a classification label.');
                    }
                } catch (InvalidArgumentException) {
                    $error = ['code' => 'invalid_prediction'];
                }
            }
            if ($error !== null) { $prediction = null; ++$failures; }
            $matched = $error === null && $prediction === $row['label'];
            $correct += (int) $matched;
            $elapsed = (float) Num::make($elapsed)->add($duration)->val();
            $predictions[] = [...$row, 'predicted' => $prediction, 'correct' => $matched,
                'latency_ms' => $duration, 'error' => $error];
        }
        $count = Arr::count($rows);
        return [...$model->identity(),
            'training_lineage' => Arr::make($model->training()->toArray()['examples'])
                ->map(static fn (array $row): array => [
                    'experience_id' => $row['experience_id'], 'outcome_id' => $row['outcome_id'],
                ])->val(),
            'samples' => $count, 'correct' => $correct, 'failures' => $failures,
            'accuracy' => $count > 0 ? (float) Num::make($correct)->divide($count)->val() : null,
            'mean_latency_ms' => $count > 0 ? (float) Num::make($elapsed)->divide($count)->val() : null,
            'cost' => null, 'energy' => null, 'predictions' => $predictions,
        ];
    }

    private function isLabel(mixed $value): bool
    {
        // TrainingBatch and prediction snapshots already reject runtime objects.
        return Str::is($value) || Flag::isBool($value) || Num::isInt($value)
            || (Num::isFloat($value) && Num::check($value, 'is_finite'));
    }

    private function evidenceKeys(array $row): array
    {
        // Plain snapshot data only. Serialize preserves scalar types and array order;
        // this fingerprint is never decoded and does not imply semantic deduplication.
        return ['experience:' . $row['experience_id'], 'outcome:' . $row['outcome_id'],
            'sample:' . hash('sha256', serialize($row['sample']))];
    }
}
