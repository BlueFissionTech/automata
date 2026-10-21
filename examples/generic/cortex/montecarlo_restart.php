<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/scenarios/GameOracle.php';

use BlueFission\{Arr, Num};
use BlueFission\Data\Storage\Disk;
use BlueFission\Automata\Context;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{Experience, Outcome};
use BlueFission\Automata\MonteCarlo\{ReturnMemory, Search};
use BlueFission\Automata\Sensory\InputSignature;
use CortexExample\Scenarios\GameOracle;

/** A small first-move learning experiment, not a general game policy. */
final class ReturnGame
{
    /** The modulo projection is host fixture policy, never a ReturnMemory rule. */
    public static function context(int $stones, string $version = '1'): string
    {
        return (new InputSignature('first-move-stones', $version, ['remainder' => $stones % 4]))->key();
    }

    /** Freeze action semantics with an explicit version for retained evidence. */
    public static function action(int $take): array { return ['take' => $take, 'version' => '1']; }

    /** Deliberately imperfect simulation remains separate from observed game results. */
    public static function choose(ReturnMemory $memory, int $stones, string $version = '1'): array
    {
        $actions = Arr::make([1, 2, 3])->filter(static fn ($take) => $take <= $stones)->map(self::action(...))->values()->val();
        $rollouts = (new Search(30, 42))->evaluate($actions,
            static fn (array $action) => $action['take'] === 1 ? 0.55 : 0.5);
        return $memory->rank($rollouts, self::context($stones, $version));
    }

    /** Execute a legal episode; fixed optimal continuation isolates the first-move node. */
    public static function play(int $initial, int $first): array
    {
        $remaining = $initial; $actor = 'agent'; $moves = [];
        while ($remaining > 0) {
            $take = $moves === [] ? $first : (GameOracle::winningMoves($remaining)[0] ?? 1);
            $after = (int) Num::make($remaining)->subtract($take)->val();
            $moves[] = ['actor' => $actor, 'before' => $remaining, 'take' => $take, 'after' => $after];
            $remaining = $after;
            if ($remaining === 0) { break; }
            $actor = $actor === 'agent' ? 'opponent' : 'agent';
        }
        return ['initial' => $initial, 'moves' => $moves, 'winner' => $actor];
    }

    /** Observed episodes require transcript validation; simulated returns identify their estimator. */
    public static function session(int $stones, int $take, string $origin = 'observed', string $suffix = ''): Experience
    {
        if ($origin === 'observed') {
            $episode = self::play($stones, $take);
            $evaluation = GameOracle::evaluate($episode);
            if (!$evaluation['valid']) { throw new RuntimeException('Invalid fixture episode.'); }
            $observations = ['episode' => $episode, 'evaluation' => $evaluation];
        } else {
            $observations = ['estimated_return' => 1.0, 'simulation_id' => 'biased-initial-v1'];
        }
        $id = 'game-' . $stones . '-' . $take . '-' . $origin . $suffix;
        return Experience::fromStatements($id, [], new Context(['initial' => $stones]), [
            'timestamp' => '2026-10-20T00:00:00Z',
            // These are the actual fixture admission/projection decisions, retained
            // with the episode instead of inferred later from a changed policy.
            'provenance' => ['admission' => ['status' => 'approved', 'policy' => 'fixture-returns-v1'],
                'node' => ['id' => 'first-move', 'version' => '1'], 'origin' => $origin],
            'metadata' => ['signature' => (new InputSignature('first-move-stones', '1', ['remainder' => $stones % 4]))->toArray()],
        ])
            ->withOutcome(new Outcome('return', $id, $origin === 'observed' ? 'transcript-evaluator' : 'rollout-estimator', true, $observations,
                ['action' => self::action($take), 'context_key' => self::context($stones), 'origin' => $origin]));
    }

    /** Held-out initial states are executed and scored, never admitted as training. */
    public static function evaluate(ReturnMemory $memory, array $states): array
    {
        return Arr::make($states)->map(static function (int $stones) use ($memory): array {
            $ranking = self::choose($memory, $stones);
            $episode = self::play($stones, $ranking[0]['action']['take']);
            return ['ranking' => $ranking, 'episode' => $episode, 'evaluation' => GameOracle::evaluate($episode)];
        })->val();
    }
}

$phase = $argv[1] ?? 'coordinate';
if ($phase !== 'coordinate') {
    if (!Arr::has(['learn', 'continue', 'restore'], $phase, true)) { throw new InvalidArgumentException('Unknown phase.'); }
    $path = $argv[2] ?? throw new InvalidArgumentException('Private checkpoint path required.');
    $disk = new Disk(['location' => dirname($path), 'name' => basename($path)]);
    $disk->activate();
    $memory = new ReturnMemory(policyId: 'stone-return-fixture');
    if ($phase !== 'learn') { $disk->read(); $memory = $memory->restore($disk->contents()); }
    $before = $memory->checkpoint();
    $replays = 0;
    foreach ($before['evidence'] as $entry) {
        $signal = $entry['reward'] === null ? null : new FeedbackSignal($entry['reward']);
        if (!$memory->learn(new Experience($entry['experience']), $entry['outcome_id'], $signal)) { ++$replays; }
    }
    $replayUnchanged = $before === $memory->checkpoint();
    $baseline = ReturnGame::evaluate(new ReturnMemory(), [18, 22, 19, 23]);
    $admitted = 0;
    if ($phase !== 'restore') {
        foreach ($phase === 'learn' ? [6, 10, 14] : [7, 11, 15] as $stones) {
            // Explicit bounded exploration exercises every legal first action.
            foreach ([1, 2, 3] as $take) {
                $experience = ReturnGame::session($stones, $take);
                $won = $experience->outcomes()[0]->observations()['evaluation']['won'];
                $admitted += (int) $memory->learn($experience, 'return', new FeedbackSignal($won ? 1.0 : -1.0));
            }
        }
        if ($phase === 'learn') {
            // Contradictory simulated returns cannot overwrite observed outcomes.
            foreach ([6, 10, 14] as $stones) {
                $memory->learn(ReturnGame::session($stones, 1, 'simulated'), 'return', FeedbackSignal::positive());
            }
            $memory->learn(ReturnGame::session(6, 1, suffix: '-unrated'), 'return', null);
            $memory->learn(ReturnGame::session(6, 1, suffix: '-neutral'), 'return', new FeedbackSignal(0.0));
        }
    }
    $report = ['pid' => getmypid(), 'admitted' => $admitted, 'replays' => $replays, 'replay_unchanged' => $replayUnchanged,
        'baseline' => $baseline, 'held_out' => ReturnGame::evaluate($memory, [18, 22, 19, 23]),
        'unseen' => ReturnGame::choose($memory, 17)[0]['action']['take'],
        'new_projection' => ReturnGame::choose($memory, 18, '2')[0]['action']['take'],
        'observed' => $memory->statistics(ReturnGame::context(6), ReturnGame::action(1)),
        'simulated' => $memory->statistics(ReturnGame::context(6), ReturnGame::action(1), 'simulated'),
        'checkpoint' => $memory->checkpoint()];
    // Clean-restart proof only: storage authentication, atomic commit and ownership are host concerns.
    $disk->contents(json_encode($report['checkpoint'], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    $disk->write();
    echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
    exit(0);
}

$path = tempnam(sys_get_temp_dir(), 'cortex-returns-');
try {
    $runs = [];
    foreach (['learn', 'continue', 'restore'] as $phase) {
        $process = proc_open([PHP_BINARY, __FILE__, $phase, $path], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start return-memory probe.'); }
        fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException('Return-memory child failed: ' . $error); }
        $runs[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $wins = static fn (array $rows): int => Arr::make($rows)->filter(static fn ($row) => $row['evaluation']['valid'] && $row['evaluation']['won'])->size();
    $checks = [
        'baseline_loses_independent_games' => $wins($runs[0]['baseline']) === 0,
        'first_family_improves' => $wins($runs[0]['held_out']) === 2,
        'restart_adds_family_preserving_prior' => $wins($runs[1]['held_out']) === 4,
        'second_restart_retains_wins' => $wins($runs[2]['held_out']) === 4,
        'second_restart_learns_nothing' => $runs[2]['admitted'] === 0,
        'checkpoint_retained_exactly' => $runs[1]['checkpoint'] === $runs[2]['checkpoint'],
        'replay_is_idempotent' => $runs[1]['replay_unchanged'] && $runs[2]['replay_unchanged'] && $runs[2]['replays'] === Arr::size($runs[1]['checkpoint']['evidence']),
        'simulated_returns_stay_separate' => $runs[2]['simulated']['mean_reward'] === 1.0 && $runs[2]['observed']['mean_reward'] < 0.0,
        'neutral_return_retained' => $runs[2]['observed']['neutral_samples'] === 1,
        'unrated_return_retained' => $runs[2]['observed']['unrated_samples'] === 1,
        'unseen_context_keeps_anchor' => $runs[2]['unseen'] === 1,
        'new_projection_keeps_anchor' => $runs[2]['new_projection'] === 1,
    ];
    $passed = !Arr::has($checks, false, true);
    echo json_encode(['passed' => $passed, 'checks' => $checks, 'runs' => $runs,
        'scope' => 'Observed first-move returns alter bounded Monte Carlo rankings across three processes. Synthetic game, hand-configured signature and fixed continuation; no general game learning, calibrated confidence, automatic activation or crash-safe storage claim.'],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
} finally { if (is_file($path)) { unlink($path); } }
exit($passed ? 0 : 1);
