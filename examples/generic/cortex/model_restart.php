<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Data\Storage\Disk;
use BlueFission\Automata\Context;
use BlueFission\Automata\Learning\{ClassificationEvaluator, Experience, LearningCoordinator, ModelCandidate, Outcome, TrainingBatch, TrainingExample, TrainingPolicy, TrainingTrigger};
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Strategy\{DecisionTreeClassification, IStrategy, KNearestPrediction, NaiveBayesTextClassification};

/** Synthetic, independently labelled local-node sessions; no provider weights change. */
final class LocalModelSessions
{
    /** Exact feature/projection identity is declared independently for each model. */
    public static function batch(string $kind, string $family, bool $holdout = false): TrainingBatch
    {
        $numeric = Arr::has(['knn', 'tree'], $kind, true);
        $rows = match ($family) {
            'anchor' => $numeric ? [[0.0], [1.0]] : ['quiet routine hello', 'quiet normal welcome'],
            'urgent' => $numeric ? [[8.0], [9.0]] : ['urgent danger alarm', 'urgent danger critical'],
            'restricted' => $numeric ? [[-8.0], [-9.0]] : ['restricted forbidden private', 'restricted forbidden secret'],
            default => throw new InvalidArgumentException('Unknown fixture family.'),
        };
        if ($holdout) {
            $rows = $numeric ? Arr::make($rows)->map(static fn ($row) => [$row[0] + 0.2])->val()
                : Arr::make($rows)->map(static fn ($text) => $text . ' request')->val();
        }
        $label = match ($family) { 'anchor' => 'wait', 'urgent' => 'assist', 'restricted' => 'decline' };
        $examples = [];
        foreach ($rows as $index => $sample) {
            // A label is an explicit reviewed target, never the fact execution succeeded.
            $id = ($holdout ? 'holdout-' : 'session-') . $family . '-' . $index;
            $examples[] = new TrainingExample($id, 'label-' . $id, $sample, $label);
        }
        return new TrainingBatch('local-session-' . $kind, '1', $examples);
    }

    /** Fresh local instances prevent candidate training from modifying the anchor. */
    public static function model(string $kind): IStrategy
    {
        return match ($kind) { 'knn' => new KNearestPrediction(1), 'bayes' => new NaiveBayesTextClassification(),
            'tree' => new DecisionTreeClassification(3),
            default => throw new InvalidArgumentException('Unknown fixture model.') };
    }

    /** All admitted rows train the model; held-out examples never enter this callback. */
    public static function train(IStrategy $model, TrainingBatch $batch): void
    {
        $model->train($batch->samples(), $batch->labels(), 0.0);
    }

    /** Compare models on disjoint labelled sessions using the library evaluator. */
    public static function evaluate(ModelCandidate $anchor, ModelCandidate $candidate, TrainingBatch $holdout): array
    {
        return (new ClassificationEvaluator(minimumSamples: 4, minimumAccuracy: 1.0))->compare($anchor, $candidate, $holdout);
    }
}

$mode = $argv[1] ?? 'coordinate';
if ($mode !== 'coordinate') {
    if (!Arr::has(['learn', 'continue', 'restore'], $mode, true)) { throw new InvalidArgumentException('Unknown phase.'); }
    $directory = $argv[2] ?? throw new InvalidArgumentException('Private fixture directory required.');
    $disk = new Disk(['location' => $directory, 'name' => 'training.json']);
    $disk->activate();
    $saved = ['owners' => [], 'artifacts' => [], 'sessions' => []];
    if ($mode !== 'learn') { $disk->read(); $saved = $disk->contents(); }
    $reports = [];
    foreach (['knn', 'bayes', 'tree'] as $kind) {
        $prior = LocalModelSessions::model($kind);
        $initialBatch = LocalModelSessions::batch($kind, 'anchor');
        LocalModelSessions::train($prior, $initialBatch);
        $anchor = new ModelCandidate($kind, 'anchor', $prior, $initialBatch);
        $owner = new LearningCoordinator($anchor, new TrainingPolicy(minimumExamples: 2, minimumNewExamples: 2));
        $loads = 0;
        if ($mode !== 'learn') {
            $owner = $owner->restore($saved['owners'][$kind], static function (array $identity, TrainingBatch $batch) use ($directory, $kind, $saved, &$loads): IStrategy {
                // The host binds exact identities to private, locally produced artifacts.
                // PHP-ML files contain serialized models: never load untrusted bytes.
                if ($identity['id'] !== $kind || !Arr::has(['anchor', 'v1', 'v2'], $identity['version'], true)) {
                    throw new RuntimeException('Unexpected fixture model identity.');
                }
                $name = $kind . '-' . $identity['version'] . '.model';
                $path = $directory . '/' . $name;
                if (!isset($saved['artifacts'][$name]) || !hash_equals($saved['artifacts'][$name], hash_file('sha256', $path))) {
                    throw new RuntimeException('Model artifact is missing or changed.');
                }
                $model = LocalModelSessions::model($kind);
                if (!$model->loadModel($path)) { throw new RuntimeException('Model could not be loaded.'); }
                ++$loads;
                return $model;
            });
        }
        $trained = 0;
        $firstBatch = $initialBatch->merge(LocalModelSessions::batch($kind, 'urgent'));
        $batch = $mode === 'continue' ? $owner->evidence()->merge(LocalModelSessions::batch($kind, 'restricted')) : $firstBatch;
        $version = $mode === 'continue' ? 'v2' : 'v1';
        $never = static fn () => throw new RuntimeException('Restore/replay must not train or authorize again.');
        if ($mode !== 'restore') {
            $acting = $mode === 'learn' ? $anchor
                : $owner->train('train-v1', 'v1', $firstBatch, new TrainingTrigger(), $never, $never, $never)->candidate();
            $newRows = LocalModelSessions::batch($kind, $mode === 'learn' ? 'urgent' : 'restricted');
            $reviews = [];
            foreach ($newRows->toArray()['examples'] as $row) {
                $prediction = $acting->strategy()->predict($row['sample']);
                // Execution completed, but its independently reviewed target may
                // disagree. Keep the signed reward separate from that target label.
                $experience = Experience::fromStatements($row['experience_id'], [], new Context(['sample' => $row['sample']]),
                    ['timestamp' => '2026-10-20T00:00:00Z'])
                    ->withOutcome(new Outcome($row['outcome_id'], $row['experience_id'], 'fixture-reviewer', true,
                        ['prediction' => $prediction, 'target' => $row['label'], 'reward' => $prediction === $row['label'] ? 1 : -1],
                        ['model' => $acting->identity(), 'projection_id' => $newRows->toArray()['projection_id'], 'projection_version' => '1']));
                $reviews[] = $experience->toArray();
            }
            $saved['sessions'][$kind][$version] = $reviews;
            $result = $owner->train('train-' . $version, $version, $batch, new TrainingTrigger(),
                static fn () => LocalModelSessions::model($kind),
                static function (IStrategy $model, TrainingBatch $rows) use (&$trained): void {
                    ++$trained;
                    LocalModelSessions::train($model, $rows);
                }, static fn () => GovernanceDecision::approved());
            if ($result->status() !== 'trained') { throw new RuntimeException('Training did not complete.'); }
            foreach ($mode === 'learn' ? [$anchor, $result->candidate()] : [$result->candidate()] as $candidate) {
                $name = $kind . '-' . $candidate->identity()['version'] . '.model';
                if (!$candidate->strategy()->saveModel($directory . '/' . $name)) { throw new RuntimeException('Artifact save failed.'); }
                $saved['artifacts'][$name] = hash_file('sha256', $directory . '/' . $name);
            }
        }
        // An old request is replayed after new training too; it cannot trigger a fit.
        $replay = $owner->train('train-v1', 'v1', $firstBatch, new TrainingTrigger(), $never, $never, $never);
        $candidate = $mode === 'learn' ? $replay->candidate()
            : $owner->train('train-v2', 'v2', $owner->evidence(), new TrainingTrigger(), $never, $never, $never)->candidate();
        $holdout = LocalModelSessions::batch($kind, 'anchor', true)->merge(LocalModelSessions::batch($kind, 'urgent', true));
        if ($mode !== 'learn') { $holdout = $holdout->merge(LocalModelSessions::batch($kind, 'restricted', true)); }
        $report = LocalModelSessions::evaluate($anchor, $candidate, $holdout);
        $reports[$kind] = ['evaluation' => $report, 'loads' => $loads, 'trains' => $trained,
            'retained_examples' => Arr::size($owner->evidence()->samples()), 'candidate' => $candidate->identity(),
            'replay_status' => $replay->status(), 'sessions' => $saved['sessions'][$kind]];
        $saved['owners'][$kind] = $owner->checkpoint();
    }
    // Clean-restart fixture only. Atomic publication/recovery is qualified separately.
    // Disk::contents() is not fluent in the supported dependency contract.
    $disk->contents(json_encode($saved, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    $disk->write();
    echo json_encode(['pid' => getmypid(), 'reports' => $reports], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
    exit(0);
}

$directory = sys_get_temp_dir() . '/cortex-local-models-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) { throw new RuntimeException('Cannot create private fixture directory.'); }
try {
    $runs = [];
    foreach (['learn', 'continue', 'restore'] as $phase) {
        $process = proc_open([PHP_BINARY, __FILE__, $phase, $directory],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start training probe.'); }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException('Training child failed: ' . $error); }
        $runs[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $checks = [];
    foreach (['knn', 'bayes', 'tree'] as $kind) {
        $first = $runs[0]['reports'][$kind]; $second = $runs[1]['reports'][$kind]; $third = $runs[2]['reports'][$kind];
        $checks[$kind . '_first_session_improves_holdout'] = $first['evaluation']['recommended'];
        $checks[$kind . '_restart_adds_new_family_without_forgetting'] = $second['evaluation']['recommended'] && $second['evaluation']['candidate']['accuracy'] === 1.0;
        $checks[$kind . '_second_restart_retains_behavior'] = $third['evaluation']['candidate']['accuracy'] === 1.0;
        $checks[$kind . '_real_artifacts_loaded'] = $second['loads'] === 2 && $third['loads'] === 3;
        $checks[$kind . '_only_new_request_trains'] = $first['trains'] === 1 && $second['trains'] === 1 && $third['trains'] === 0;
        $checks[$kind . '_anchor_and_sessions_retained'] = $first['retained_examples'] === 4 && $second['retained_examples'] === 6 && $third['retained_examples'] === 6;
        $checks[$kind . '_request_replay_retained'] = $third['replay_status'] === 'trained' && $third['candidate']['version'] === 'v2';
        $checks[$kind . '_reviewed_session_evidence_retained'] = $second['sessions'] === $third['sessions']
            && Arr::size($third['sessions']['v1']) === 2 && Arr::size($third['sessions']['v2']) === 2;
    }
    $passed = !Arr::has($checks, false, true);
    echo json_encode(['passed' => $passed, 'checks' => $checks, 'runs' => $runs,
        'scope' => 'KNN, Naive Bayes and fitted decision-tree artifact restoration, retained labelled-session training and disjoint holdout evaluation in three processes. Synthetic fixture families; no automatic activation, generalization claim, Monte Carlo or crash-safe publication.'],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
} finally {
    // Delete only known files inside the exact scratch directory created above.
    foreach (['knn', 'bayes', 'tree'] as $kind) {
        foreach (['anchor', 'v1', 'v2'] as $version) { $path = $directory . '/' . $kind . '-' . $version . '.model'; if (is_file($path)) { unlink($path); } }
    }
    if (is_file($directory . '/training.json')) { unlink($directory . '/training.json'); }
    rmdir($directory);
}
exit($passed ? 0 : 1);
