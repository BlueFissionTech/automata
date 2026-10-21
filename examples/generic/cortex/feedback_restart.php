<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/CompoundCortex.php';

use BlueFission\{Arr, Num};
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{Experience, FileBundleStore};
use BlueFission\Automata\Support\RecordSnapshot;
use BlueFission\Examples\Cortex\CompoundCortex;

/**
 * Example-owned stress protocol for an anchored route policy, not a model fitter.
 * Sensor encodings, review noise and cost units are synthetic and configurable
 * fixture choices. The same input distribution deliberately receives a changed
 * external objective; an unobserved change cannot be inferred before feedback.
 */
final class FeedbackCortex
{
    /** Capture different frames with equivalent signatures; no evaluation labels enter input. */
    public static function input(string $text, ?int $lights, bool $extra = false): array
    {
        $segments = [['type' => 'text', 'payload' => $text, 'meta' => ['mime' => 'text/plain']]];
        if ($lights !== null) {
            $pixels = [];
            for ($index = 0; $index < 10; ++$index) { $pixels[] = $index < $lights ? 1 : 0; }
            $segments[] = ['type' => 'video', 'payload' => $pixels, 'meta' => ['mime' => 'video/example-binary']];
        }
        if ($extra) { $segments[] = ['type' => 'telemetry', 'payload' => '0', 'meta' => ['mime' => 'application/example']]; }
        return ['segments' => $segments];
    }

    /** Independent task outcome, not selected route, determines the fixture reward. */
    public static function correct(array $run, bool $includeMedia): bool
    {
        return $run['output'] === ['intent' => 'assist', 'media_confirmed' => $includeMedia];
    }

    /**
     * Frozen novel frames plus unchanged text-only/blank-sensor tasks never train.
     * Cost is an explicit declared response-work proxy (1 versus 4 units), not
     * measured latency, billing, or total graph cost. Utility is only a fixture
     * tradeoff: correctness minus 0.1 times mean response work.
     */
    public static function evaluate(CompoundCortex $agent, bool $includeMedia): array
    {
        $rows = []; $correct = 0; $cost = 0;
        foreach ([3, 5, 7, 9, null, null, 0, 1] as $index => $lights) {
            $run = $agent->run('evaluation-' . $index, self::input('help assess heldout ' . $index, $lights));
            $expectedMedia = $index < 4 && $includeMedia;
            $ok = self::correct($run, $expectedMedia);
            $units = $run['selected']['id'] === 'response.quick' ? 1 : 4;
            $correct += (int) $ok; $cost += $units;
            $rows[] = ['correct' => $ok, 'selected' => $run['selected'], 'cost_units' => $units,
                'context' => $run['context_key'], 'expected_media' => $expectedMedia, 'actual' => $run['output']];
        }
        $count = Arr::size($rows);
        return ['correct' => $correct, 'total' => $count, 'mean_cost' => Num::make($cost)->divide($count)->val(),
            'utility' => Num::make($correct)->divide($count)->subtract(Num::make($cost)->divide($count)->multiply(0.1)->val())->val(), 'rows' => $rows];
    }

    /**
     * Execute a batch before applying its reviews, preserving delayed attribution.
     * One in seven reviews is deliberately inverted. Each observation retains the
     * objective version, independent correct result, supplied reward and executed
     * Experience. Evaluation rows never enter this journal or the learner.
     */
    public static function train(CompoundCortex $agent, string $epoch, bool $includeMedia, int $count): array
    {
        $journal = []; $pending = [];
        for ($index = 0; $index < $count; ++$index) {
            $run = $agent->run($epoch . '-' . $index, self::input('help inspect training ' . $index, [4, 6, 8][$index % 3]));
            $correct = self::correct($run, $includeMedia);
            $reward = $correct ? 1 : -1;
            $noisy = $index % 7 === 6;
            if ($noisy) { $reward = -$reward; }
            $row = ['experience' => $run['experience']->toArray(), 'objective' => $epoch,
                'expected_media' => $includeMedia, 'correct' => $correct, 'noise' => $noisy, 'reward' => $reward];
            $journal[] = $row; $pending[] = $row;
            // Four completed sessions share the old policy until their review batch arrives.
            if (Arr::size($pending) === 4 || $index === $count - 1) {
                foreach ($pending as $entry) {
                    $agent->feedback(['experience' => new Experience($entry['experience'])], new FeedbackSignal($entry['reward']));
                }
                $pending = [];
            }
        }
        return $journal;
    }

    /** Inspect every recorded score against its immutable anchor and configured cap. */
    public static function bounded(CompoundCortex $agent): bool
    {
        // Reconstruct the general advisor rather than trusting reported demo scores.
        $advisor = new \BlueFission\Automata\Learning\AnchoredRouteAdvisor([
            ['id' => 'response.quick', 'version' => '1', 'weight' => 0.6],
            ['id' => 'response.careful', 'version' => '1', 'weight' => 0.5],
        ], policyId: 'compound-fixture', minimumSamples: 2, priorWeight: 2, maximumAdjustment: 0.2);
        $advisor = $advisor->restore($agent->checkpoint());
        foreach ($advisor->receipts() as $receipt) {
            $advice = $advisor->advise(new \BlueFission\Automata\Strategy\Routing\StrategyRouteRequest(['context_key' => $receipt['context_key']]),
                [['id' => 'response.quick', 'version' => '1'], ['id' => 'response.careful', 'version' => '1']]);
            foreach ($advice->rankings as $ranking) {
                if (abs($ranking['score'] - $ranking['anchor']) > 0.200000001) { return false; }
            }
        }
        return true;
    }
}

$phase = $argv[1] ?? 'coordinate';
if ($phase !== 'coordinate') {
    $directory = $argv[2] ?? throw new InvalidArgumentException('Private store directory required.');
    $store = new FileBundleStore($directory);
    $state = $store->load(); $agent = new CompoundCortex();
    if ($state !== null) { $agent->restore($state['policy']); }
    $before = FeedbackCortex::evaluate($agent, true); $details = []; $journal = $state['journal'] ?? [];
    if ($phase === 'baseline') {
        // Contradictory, neutral and missing reviews must not collapse the anchor.
        foreach ([1, -1, 1, -1, 0, null] as $index => $reward) {
            $run = $agent->run('ambiguous-' . $index, FeedbackCortex::input('help inspect ambiguity', 4));
            $agent->feedback($run, $reward === null ? null : new FeedbackSignal($reward));
        }
        $details['balanced_keeps_anchor'] = FeedbackCortex::evaluate($agent, true) === $before;
    } elseif ($phase === 'learn') {
        $journal = [...$journal, ...FeedbackCortex::train($agent, 'include-media.v1', true, 32)];
    } elseif ($phase === 'shift') {
        $details['before_shift'] = FeedbackCortex::evaluate($agent, false);
        $journal = [...$journal, ...FeedbackCortex::train($agent, 'omit-media.v2', false, 64)];
    } elseif ($phase === 'restore') {
        $checkpoint = $agent->checkpoint();
        foreach ($journal as $entry) {
            $agent->feedback(['experience' => new Experience($entry['experience'])], new FeedbackSignal($entry['reward']));
        }
        $details['replay_unchanged'] = $checkpoint === $agent->checkpoint();
    } else { throw new InvalidArgumentException('Unknown phase.'); }
    $details['bounded'] = FeedbackCortex::bounded($agent);
    $denied = $agent->run('denied', FeedbackCortex::input('help inspect denied', 6), false);
    $details['denied'] = $denied['response_calls'] === 0 && $denied['selected'] === null;
    $unknown = $agent->run('unseen', FeedbackCortex::input('help inspect unseen', 6, true));
    $details['unseen_anchor'] = $unknown['selected']['id'] === 'response.quick';
    $replacement = ['policy' => $agent->checkpoint(), 'journal' => $journal];
    if ($phase !== 'restore') { $store->compareAndSwap($state === null ? null : RecordSnapshot::fingerprint($state), $replacement); }
    echo json_encode(['pid' => getmypid(), 'before' => $before, 'include_media' => FeedbackCortex::evaluate($agent, true),
        'omit_media' => FeedbackCortex::evaluate($agent, false), 'details' => $details, 'journal' => $journal,
        'checkpoint_digest' => RecordSnapshot::fingerprint($agent->checkpoint())], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
    exit(0);
}

$directory = sys_get_temp_dir() . '/cortex-feedback-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) { throw new RuntimeException('Cannot create private feedback fixture.'); }
try {
    $runs = [];
    foreach (['baseline', 'learn', 'shift', 'restore'] as $phase) {
        $process = proc_open([PHP_BINARY, __FILE__, $phase, $directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start feedback phase.'); }
        fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException('Feedback phase ' . $phase . ' failed: ' . $error); }
        $runs[$phase] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $cold = $runs['baseline']; $learned = $runs['learn']; $shifted = $runs['shift']; $restored = $runs['restore'];
    $checks = [
        'balanced_neutral_absent_feedback_preserves_anchor' => $cold['details']['balanced_keeps_anchor'],
        'noisy_delayed_feedback_improves_untrained_frames' => $cold['include_media']['correct'] === 4 && $learned['include_media']['correct'] === 8,
        'improvement_survives_first_restart' => $shifted['before'] === $learned['include_media'],
        'unchanged_text_and_blank_sensor_tasks_remain_correct' => !Arr::has(Arr::make($learned['include_media']['rows'])->slice(4)->map(static fn ($row) => $row['correct'])->val(), false, true),
        'expensive_route_does_not_replace_every_context' => Arr::make($learned['include_media']['rows'])->map(static fn ($row) => $row['selected']['id'])->unique()->size() === 2,
        'response_work_increase_is_explicit' => $cold['include_media']['mean_cost'] == 1 && $learned['include_media']['mean_cost'] == 2.5,
        'fixture_quality_cost_tradeoff_improves' => $learned['include_media']['utility'] > $cold['include_media']['utility'],
        'changed_objective_exposes_old_policy_failure' => $shifted['details']['before_shift']['correct'] === 4,
        'continued_feedback_adapts_to_reversed_objective' => $shifted['omit_media']['correct'] === 8,
        'changed_objective_recovers_lower_response_cost' => $shifted['omit_media']['mean_cost'] == 1,
        'second_restart_retains_recovery' => $restored['omit_media'] === $shifted['omit_media'],
        'replaying_both_epochs_never_learns_twice' => $restored['details']['replay_unchanged'] && $restored['checkpoint_digest'] === $shifted['checkpoint_digest'],
        'retained_journal_includes_actual_noisy_reviews' => Arr::make($restored['journal'])->filter(static fn ($row) => $row['noise'])->size() > 0,
        'all_phases_keep_updates_bounded' => !Arr::has(Arr::make($runs)->map(static fn ($run) => $run['details']['bounded'])->val(), false, true),
        'all_phases_preserve_authority_and_unknown_context' => !Arr::has(Arr::make($runs)->map(static fn ($run) => $run['details']['denied'] && $run['details']['unseen_anchor'])->val(), false, true),
    ];
    $passed = !Arr::has($checks, false, true);
    echo json_encode(['passed' => $passed, 'checks' => $checks, 'runs' => $runs,
        'scope' => 'Synthetic route-feedback stress experiment; fixed 1-in-7 review noise and delayed batches. No model fitting, general drift detector, exploration algorithm, real media, measured latency or universal optimization claim.'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
} finally {
    // Cleanup is confined to flat files in this run's newly created private directory.
    foreach (glob($directory . '/*') as $file) { if (is_file($file)) { unlink($file); } }
    rmdir($directory);
}
exit($passed ? 0 : 1);
