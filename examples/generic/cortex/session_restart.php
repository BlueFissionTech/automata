<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/CompoundCortex.php';
require_once __DIR__ . '/SessionProjection.php';

use BlueFission\{Arr, Str};
use BlueFission\Data\Storage\Disk;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{BundleLifecycle, BundleSnapshot, Experience, ExperienceRecomposer, FileBundleStore, LearningBundle,
    LearningCoordinator, ModelCandidate, Outcome, TrainingBatch, TrainingPolicy, TrainingTrigger};
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Strategy\DecisionTreeClassification;
use BlueFission\Automata\Support\RecordSnapshot;
use BlueFission\Examples\Cortex\{CompoundCortex, SessionProjection};

/**
 * Example host connecting observed compound sessions to retained coordinated learning.
 * Labels, sensor encoding, graph builder and thresholds belong only to this fixture.
 * No remote weights, providers, real-world actions or distributed trainers are used.
 */
final class SessionCortex
{
    /** Different raw frames/text share coarse signatures without sharing session ids. */
    public static function input(string $text, int $lights, bool $extra = false): array
    {
        $pixels = [];
        for ($index = 0; $index < 10; ++$index) { $pixels[] = $index < $lights ? 1 : 0; }
        return ['segments' => [
            ['type' => 'text', 'payload' => $text, 'meta' => ['mime' => 'text/plain']],
            ['type' => 'video', 'payload' => $pixels, 'meta' => ['mime' => 'video/example-binary']],
            ...($extra ? [['type' => 'telemetry', 'payload' => '0', 'meta' => ['mime' => 'application/example']]] : []),
        ]];
    }

    /** Stable private artifact names are hashes, never paths supplied by a manifest. */
    public static function reader(string $directory): Closure
    {
        return static function (array $component) use ($directory): string {
            $key = $component['artifact']['key'];
            if (!Str::matches($key, '/^[a-f0-9]{64}$/D')) { throw new RuntimeException('Invalid artifact key.'); }
            $bytes = file_get_contents($directory . '/artifacts/' . $key);
            if ($bytes === false) { throw new RuntimeException('Artifact unavailable.'); }
            return $bytes;
        };
    }

    /** Decode only already-verified, locally produced fitted bytes with explicit classes. */
    public static function model(string $bytes): DecisionTreeClassification
    {
        $model = unserialize($bytes, ['allowed_classes' => [DecisionTreeClassification::class,
            \BlueFission\Automata\Strategy\Backend\ClassificationTree::class,
            \Phpml\Classification\DecisionTree\DecisionTreeLeaf::class, \Phpml\ModelManager::class]]);
        if (!$model instanceof DecisionTreeClassification) { throw new RuntimeException('Unsupported local model.'); }
        return $model;
    }

    /** One verified workflow recipe binds the registered builder, model and route policy. */
    public static function agent(BundleSnapshot $snapshot, ?string $modelBytes = null, ?array $policy = null): CompoundCortex
    {
        $recipe = json_decode($snapshot->artifact('workflow'), true, 512, JSON_THROW_ON_ERROR);
        if (($recipe['builder'] ?? null) !== 'compound-cortex' || ($recipe['version'] ?? null) !== '1') {
            throw new RuntimeException('Unregistered workflow builder.');
        }
        $model = self::model($modelBytes ?? $snapshot->artifact($recipe['model_slot']));
        $modelVersion = $modelBytes === null ? Str::make($recipe['model_slot'])->replace('model.', '')->val() : 'anchor';
        $agent = new CompoundCortex(static fn (array $features): string => $model->predict($features), ['id' => 'intent', 'version' => $modelVersion]);
        return $agent->restore($policy ?? json_decode($snapshot->artifact('policy'), true, 512, JSON_THROW_ON_ERROR));
    }

    /** Configured policy is stable across restoration; only admitted sessions alter state. */
    public static function trainingOwner(BundleSnapshot $snapshot): LearningCoordinator
    {
        $learning = self::learning($snapshot);
        $anchor = new ModelCandidate('intent', 'anchor', self::model($snapshot->artifact('model.anchor')),
            TrainingBatch::fromArray($learning['anchor_batch']));
        $owner = new LearningCoordinator($anchor, new TrainingPolicy(minimumExamples: 2, minimumNewExamples: 2));
        return $owner->restore($learning['coordinator'], static function (array $identity) use ($snapshot) {
            if ($identity['id'] !== 'intent') { throw new RuntimeException('Unexpected model identity.'); }
            return self::model($snapshot->artifact('model.' . $identity['version']));
        });
    }

    /** Training evidence and coordinator replay receipts belong to the same active set. */
    public static function learning(BundleSnapshot $snapshot): array
    {
        return json_decode($snapshot->artifact('learning'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Stage content-addressed immutable bytes before proposing the manifest.
     * Disk writes never publish the active pointer; BundleLifecycle owns that CAS.
     */
    public static function bundle(string $directory, string $version, array $models, string $active, array $policy, array $learning): LearningBundle
    {
        $bytes = [];
        foreach ($models as $modelVersion => $modelBytes) { $bytes['model.' . $modelVersion] = $modelBytes; }
        $bytes['policy'] = json_encode($policy, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $bytes['workflow'] = json_encode(['builder' => 'compound-cortex', 'version' => '1', 'model_slot' => 'model.' . $active], JSON_THROW_ON_ERROR);
        $bytes['learning'] = json_encode($learning, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $components = [];
        foreach ($bytes as $slot => $content) {
            $key = hash('sha256', $content);
            $path = $directory . '/artifacts/' . $key;
            if (!is_file($path)) {
                $disk = new Disk(['location' => $directory . '/artifacts', 'name' => $key]);
                $disk->activate(); $disk->contents($content); $disk->write();
            }
            if (!hash_equals($key, hash_file('sha256', $path))) { throw new RuntimeException('Staged artifact changed.'); }
            $isModel = Str::make($slot)->startsWith('model.');
            $components[$slot] = ['slot' => $slot, 'kind' => $isModel ? 'model' : $slot, 'id' => $isModel ? 'intent' : $slot,
                'version' => $isModel ? Str::make($slot)->replace('model.', '')->val() : $version,
                'artifact' => ['key' => $key, 'format' => $isModel ? 'trusted-phpml-tree.v1' : 'json.v1', 'sha256' => $key], 'requires' => []];
        }
        foreach (['policy' => ['model.' . $active], 'workflow' => ['model.' . $active, 'policy'],
            'learning' => Arr::make($components)->keys()->filter(static fn ($slot) => Str::make($slot)->startsWith('model.'))->val()] as $slot => $dependencies) {
            foreach ($dependencies as $dependency) {
                $target = $components[$dependency];
                $components[$slot]['requires'][] = ['slot' => $dependency, 'id' => $target['id'], 'version' => $target['version'], 'sha256' => $target['artifact']['sha256']];
            }
        }
        return new LearningBundle('session-cortex', $version, 'session-fixture', Arr::make($components)->values()->val());
    }

    /** Explicit human labels are distinct from signed workflow quality and completion. */
    public static function review(Experience $experience, ?string $label, ?int $reward, string $id = 'review'): Experience
    {
        return $experience->withOutcome(new Outcome($id, $experience->id(), 'fixture-reviewer', true,
            ['approved' => true, 'label' => $label, 'reward' => $reward, 'scope' => 'session-fixture', 'confidence' => 1.0,
                'reward_policy' => ['id' => 'compound-output-review', 'version' => '1']]));
    }

    /** Three novel-feature challenges and two anchor regression checks never train. */
    public static function score(CompoundCortex $agent): array
    {
        $rows = []; $correct = 0;
        foreach ([['hello quietly', 0, 'observe', false], ['hello normal', 1, 'observe', false],
            ['help interpret this new signal', 5, 'assist', true], ['help analyze this beacon', 7, 'assist', true],
            ['help explain this unknown pattern', 9, 'assist', true]] as $index => [$text, $lights, $intent, $media]) {
            $run = $agent->run('heldout-' . $index, self::input($text, $lights));
            $expected = ['intent' => $intent, 'media_confirmed' => $media];
            $correct += (int) ($run['output'] === $expected);
            $rows[] = ['expected' => $expected, 'actual' => $run['output'], 'selected' => $run['selected'], 'context' => $run['context_key'],
                'features' => $run['experience']->toArray()['context']['data']['model_features'],
                'attribution' => $run['experience']->outcomes()[0]->toArray()['attribution']];
        }
        return ['correct' => $correct, 'total' => Arr::size($rows), 'rows' => $rows];
    }
}

$phase = $argv[1] ?? 'coordinate';
if ($phase !== 'coordinate') {
    $directory = $argv[2] ?? throw new InvalidArgumentException('Private directory required.');
    $reader = SessionCortex::reader($directory);
    $owner = new BundleLifecycle(new FileBundleStore($directory . '/commits'), 'session-fixture');
    $journal = new FileBundleStore($directory . '/journal');
    $details = []; $fits = 0;
    $approve = static fn () => GovernanceDecision::approved();
    $never = static fn () => throw new RuntimeException('Restoration/replay attempted new work.');

    if ($phase === 'initialize') {
        // Initial labels anchor the agent before any reinforced session is admitted.
        $untrained = new CompoundCortex(); $anchors = [];
        foreach ([0, 1] as $index => $lights) {
            $run = $untrained->run('anchor-' . $index, SessionCortex::input('hello routine', $lights));
            $anchors[] = SessionCortex::review($run['experience'], 'observe', null);
        }
        $batch = (new ExperienceRecomposer())->compose($anchors, new SessionProjection());
        $model = new DecisionTreeClassification(3); $model->train($batch->samples(), $batch->labels(), 0.0); ++$fits;
        $training = new LearningCoordinator(new ModelCandidate('intent', 'anchor', $model, $batch), new TrainingPolicy(minimumExamples: 2, minimumNewExamples: 2));
        $learning = ['anchor_batch' => $batch->toArray(), 'coordinator' => $training->checkpoint(), 'sessions' => [], 'journal_digest' => null];
        $bundle = SessionCortex::bundle($directory, '1', ['anchor' => serialize($model)], 'anchor', $untrained->checkpoint(), $learning);
        $owner->initialize($bundle, $reader);
    } elseif ($phase === 'capture') {
        // Persist actual executions without labels; review happens in another process.
        $agent = SessionCortex::agent($owner->active($reader)); $sessions = [];
        foreach ([['a', 4], ['b', 6], ['c', 8], ['neutral', 4], ['absent', 6], ['conflict', 5]] as [$id, $lights]) {
            $run = $agent->run('session-' . $id, SessionCortex::input('help inspect ' . $id, $lights));
            $sessions[] = $run['experience']->toArray();
            $details['passes'] = Arr::make($run['passes'])->map(static fn ($pass) => ['signature' => $pass['signature']['key'], 'plan' => $pass['plan'], 'schedule' => $pass['schedule']])->val();
            $details['sensory_completions'] = $run['sensory_completions'];
        }
        $journal->compareAndSwap(null, ['sessions' => $sessions]);
        $details['journal_digest'] = RecordSnapshot::fingerprint($journal->load());
    } elseif ($phase === 'train') {
        $baseline = $owner->active($reader); $raw = $journal->load();
        $policy = SessionCortex::agent($baseline); $reviewed = [];
        foreach ($raw['sessions'] as $record) {
            $experience = new Experience($record); $id = $experience->id();
            if (Arr::has(['session-a', 'session-b', 'session-c'], $id, true)) {
                $actual = $experience->outcomes()[0]->observations()['output'];
                $reward = $actual === ['intent' => 'assist', 'media_confirmed' => true] ? 1 : -1;
                $experience = SessionCortex::review($experience, 'assist', $reward);
                $policy->feedback(['experience' => $experience], new FeedbackSignal($reward));
            } elseif ($id === 'session-neutral') {
                $experience = SessionCortex::review($experience, null, 0);
                $policy->feedback(['experience' => $experience], new FeedbackSignal(0));
            } elseif ($id === 'session-conflict') {
                $experience = SessionCortex::review(SessionCortex::review($experience, 'assist', null, 'review-a'), 'observe', null, 'review-b');
            }
            $reviewed[] = $experience;
        }
        $training = SessionCortex::trainingOwner($baseline);
        $projected = (new ExperienceRecomposer())->compose($reviewed, new SessionProjection());
        $batch = $training->evidence()->merge($projected);
        $result = $training->train('session-train', 'v1', $batch, new TrainingTrigger(), static fn () => new DecisionTreeClassification(3),
            static function ($model, TrainingBatch $evidence) use (&$fits): void { ++$fits; $model->train($evidence->samples(), $evidence->labels(), 0.0); }, $approve);
        if ($result->status() !== 'trained') { throw new RuntimeException('Session training was not admitted.'); }
        $learning = SessionCortex::learning($baseline);
        $learning['coordinator'] = $training->checkpoint();
        $learning['sessions'] = Arr::make($reviewed)->map(static fn ($experience) => $experience->toArray())->val();
        $learning['journal_digest'] = RecordSnapshot::fingerprint($raw);
        $bundle = SessionCortex::bundle($directory, '2', ['anchor' => $baseline->artifact('model.anchor'), 'v1' => serialize($result->candidate()->strategy())],
            'v1', $policy->checkpoint(), $learning);
        $candidate = BundleSnapshot::resolve($bundle, $reader);
        $details['model_only'] = SessionCortex::score(SessionCortex::agent($candidate, policy: json_decode($baseline->artifact('policy'), true, 512, JSON_THROW_ON_ERROR)));
        $details['policy_only'] = SessionCortex::score(SessionCortex::agent($candidate, modelBytes: $baseline->artifact('model.anchor')));
        $evaluate = static function (BundleSnapshot $old, BundleSnapshot $new): array {
            $before = SessionCortex::score(SessionCortex::agent($old)); $after = SessionCortex::score(SessionCortex::agent($new));
            return ['accepted' => $after['correct'] === 5 && $after['correct'] > $before['correct'], 'evidence' => ['baseline' => $before, 'candidate' => $after]];
        };
        $denied = $owner->activate('denied-promotion', 0, $bundle, $evaluate, static fn () => GovernanceDecision::denied(), $reader);
        $details['denied_revision'] = $owner->revision(); $details['denied'] = $denied['applied'] === false;
        $owner->activate('session-promote', 0, $bundle, $evaluate, $approve, $reader);
        $details['projected'] = $projected->toArray();
        $details['reviewed'] = $learning['sessions'];
    } elseif ($phase === 'restore') {
        $snapshot = $owner->active($reader); $training = SessionCortex::trainingOwner($snapshot);
        $result = $training->train('session-train', 'v1', $training->evidence(), new TrainingTrigger(), $never, $never, $never);
        $details['training_replay'] = $result->status();
        $receipt = $owner->activate('session-promote', 0, $snapshot->manifest(), $never, $never, $never);
        $details['promotion_replay'] = $receipt['revision'];
        $agent = SessionCortex::agent($snapshot); $unchanged = $agent->checkpoint();
        $sessions = SessionCortex::learning($snapshot)['sessions'];
        foreach (Arr::make($sessions)->slice(0, 3)->val() as $record) {
            $agent->feedback(['experience' => new Experience($record)], FeedbackSignal::negative(1));
        }
        $details['feedback_replay_unchanged'] = $unchanged === $agent->checkpoint();
        $details['retained_examples'] = Arr::size($training->evidence()->samples());
        $details['journal_retained'] = SessionCortex::learning($snapshot)['journal_digest'] === RecordSnapshot::fingerprint($journal->load());
        $denied = $agent->run('denied', SessionCortex::input('help analyze marker', 7), false);
        $details['execution_denied'] = $denied['selected'] === null && $denied['response_calls'] === 0;
        $unknown = $agent->run('unseen', SessionCortex::input('help analyze marker', 7, true));
        $details['unseen_keeps_anchor'] = $unknown['selected']['id'] === 'response.quick';
    } elseif ($phase === 'rollback') {
        $owner->rollback('session-rollback', 1, $approve, $reader);
    } elseif ($phase !== 'verify-rollback') { throw new InvalidArgumentException('Unknown session phase.'); }

    $active = $owner->active($reader);
    echo json_encode(['pid' => getmypid(), 'fits' => $fits, 'revision' => $owner->revision(), 'bundle' => $active->manifest()->identity(),
        'score' => SessionCortex::score(SessionCortex::agent($active)), 'details' => $details, 'receipts' => $owner->receipts()], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
    exit(0);
}

$directory = sys_get_temp_dir() . '/cortex-session-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) { throw new RuntimeException('Cannot create private session fixture.'); }
foreach (['commits', 'journal', 'artifacts'] as $child) { if (!mkdir($directory . '/' . $child, 0700)) { throw new RuntimeException('Cannot create fixture store.'); } }
try {
    $runs = [];
    foreach (['initialize', 'capture', 'train', 'restore', 'rollback', 'verify-rollback'] as $phase) {
        $process = proc_open([PHP_BINARY, __FILE__, $phase, $directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start session phase.'); }
        fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException('Session phase ' . $phase . ' failed: ' . $error); }
        $runs[$phase] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $capture = $runs['capture']['details']; $trained = $runs['train']; $restored = $runs['restore'];
    $trainingFeatures = Arr::make($trained['details']['projected']['examples'])->map(static fn ($row) => $row['sample'][0])->val();
    $novelFeatures = Arr::make($trained['score']['rows'])->slice(2)->map(static fn ($row) => $row['features'][0])->val();
    $checks = [
        'compound_input_observed' => $capture['sensory_completions'] === 2,
        'signatures_evolve_across_passes' => $capture['passes'][0]['signature'] !== $capture['passes'][1]['signature'] && $capture['passes'][1]['signature'] !== $capture['passes'][2]['signature'],
        'enrichment_branches_join_after_waiting' => $capture['passes'][1]['schedule'][1]['nodes'] === ['text.sentiment', 'media.marker'] && $capture['passes'][1]['schedule'][1]['ready_after_first'] === [],
        'capture_does_not_train_or_activate' => $runs['capture']['fits'] === 0 && $runs['capture']['revision'] === 0,
        'review_occurs_after_process_restart' => $runs['capture']['pid'] !== $trained['pid'],
        'only_unambiguous_reviewed_sessions_train' => Arr::size($trained['details']['projected']['examples']) === 3,
        'challenge_features_are_disjoint_from_training' => Arr::make($trainingFeatures)->intersect($novelFeatures)->val() === [],
        'retained_labels_and_rewards_have_separate_attribution' => $trained['details']['reviewed'][0]['outcomes'][1]['observations']['reward'] === -1
            && $trained['details']['reviewed'][0]['outcomes'][0]['successful'] === true
            && $trained['details']['reviewed'][0]['outcomes'][0]['attribution']['model'] === null,
        'executed_model_and_workflow_versions_are_recorded' => $restored['score']['rows'][2]['attribution']['model'] === ['id' => 'intent', 'version' => 'v1']
            && Str::make($restored['score']['rows'][2]['attribution']['workflow_version'])->startsWith('1.'),
        'workflow_change_alone_is_insufficient' => $trained['details']['policy_only']['correct'] === 2,
        'model_change_alone_is_insufficient' => $trained['details']['model_only']['correct'] === 2,
        'denied_promotion_preserves_anchor' => $trained['details']['denied'] && $trained['details']['denied_revision'] === 0,
        'combined_learning_improves_frozen_holdout' => $runs['initialize']['score']['correct'] === 2 && $trained['score']['correct'] === 5,
        'prior_capabilities_remain_correct' => $trained['score']['rows'][0]['actual'] === $trained['score']['rows'][0]['expected'] && $trained['score']['rows'][1]['actual'] === $trained['score']['rows'][1]['expected'],
        'candidate_fits_once' => $trained['fits'] === 1 && $restored['fits'] === 0 && $restored['details']['training_replay'] === 'trained',
        'rehearsal_retains_anchor_examples' => $restored['details']['retained_examples'] === 5,
        'journal_and_feedback_survive_restart' => $restored['details']['journal_retained'] && $restored['details']['feedback_replay_unchanged'],
        'promotion_replay_is_idempotent' => $restored['details']['promotion_replay'] === 1 && Arr::size($restored['receipts']) === 2,
        'fresh_process_retains_improved_behavior' => $restored['score']['correct'] === 5,
        'learning_does_not_grant_authority' => $restored['details']['execution_denied'],
        'unseen_signature_retains_configured_anchor' => $restored['details']['unseen_keeps_anchor'],
        'rollback_restores_prior_behavior' => $runs['rollback']['score']['correct'] === 2 && $runs['rollback']['revision'] === 2,
        'rollback_is_retained_without_fitting' => $runs['verify-rollback']['score']['correct'] === 2 && $runs['verify-rollback']['fits'] === 0,
    ];
    $passed = !Arr::has($checks, false, true);
    echo json_encode(['passed' => $passed, 'checks' => $checks, 'runs' => $runs,
        'scope' => 'Synthetic compound sensor sessions, delayed explicit feedback, local tree fitting and coordinated publication in six processes. Fixture projection and review policy; no general vision, statistical generalization, distributed training or exactly-once fitting across trainer crashes.'],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
} finally {
    // Remove only flat files in the exact private directories created by this run.
    foreach (['commits', 'journal', 'artifacts'] as $child) {
        foreach (glob($directory . '/' . $child . '/*') as $file) { if (is_file($file)) { unlink($file); } }
        rmdir($directory . '/' . $child);
    }
    rmdir($directory);
}
exit($passed ? 0 : 1);
