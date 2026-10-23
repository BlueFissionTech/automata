<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Data\Storage\Disk;
use BlueFission\Automata\Context;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{AnchoredRouteAdvisor, Experience, Outcome};
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouteRequest, StrategyRouter, StrategyUsage};

/** A synthetic, side-effect-free inspection action; these labels are fixture data. */
final class ReinforcementInspectionAdapter implements IStrategyRouteAdapter
{
    /** Each fixture action has a distinct exact strategy identity. */
    public function __construct(private readonly string $id) {}
    /** Declare availability explicitly; unknown adapters must never execute. */
    public function definition(): StrategyDefinition
    {
        return new StrategyDefinition(['id' => $this->id, 'version' => '1',
            'capability_id' => 'inspect', 'capability_version' => '1',
            'mode' => 'deterministic', 'side_effect_free' => true, 'availability' => 'available']);
    }
    /** This fixture has no additional input eligibility restrictions. */
    public function eligibility(StrategyRouteRequest $request): StrategyEligibility
    {
        return new StrategyEligibility(['eligible' => true]);
    }
    /** One local synthetic action consumes one invocation. */
    public function estimate(StrategyRouteRequest $request): StrategyUsage
    {
        return new StrategyUsage(['invocations' => 1]);
    }
    /** Expose the performed action, independently of the later review reward. */
    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        return new StrategyAdapterResult(['status' => 'completed', 'output' => $this->id,
            'usage' => ['invocations' => 1]]);
    }
}

$mode = $argv[1] ?? 'coordinate';
if ($mode !== 'coordinate') {
    $path = $argv[2] ?? throw new InvalidArgumentException('Checkpoint path required.');
    if (!Arr::has(['learn', 'restore'], $mode, true)) { throw new InvalidArgumentException('Unknown mode.'); }
    $definitions = [['id' => 'inspect.text', 'version' => '1', 'weight' => 0.6],
        ['id' => 'inspect.media', 'version' => '1', 'weight' => 0.5]];
    $advisor = new AnchoredRouteAdvisor($definitions, policyId: 'restart-fixture', priorWeight: 2,
        maximumAdjustment: 0.2, minimumSamples: 2);
    $router = new StrategyRouter([new ReinforcementInspectionAdapter('inspect.text'),
        new ReinforcementInspectionAdapter('inspect.media')], $advisor);
    $route = static fn (string $context): string => $router->route(new StrategyRouteRequest([
        'id' => 'request', 'subject_id' => 'fixture-agent', 'capability_id' => 'inspect', 'capability_version' => '1',
        'candidates' => $definitions, 'selection_policy' => 'adaptive', 'context_key' => $context,
    ]), new AutonomyDecision(['allowed' => true, 'subject_id' => 'fixture-agent',
        'code' => AutonomyDecision::CODE_ALLOWED,
        'capability_id' => 'inspect', 'capability_version' => '1']))->output;
    $before = $route('media-review.v1');
    $storage = new Disk(['location' => dirname($path), 'name' => basename($path)]);
    $storage->activate();
    $replays = 0;
    if ($mode === 'learn') {
        // Three actual completed route sessions receive explicit negative review.
        // The reviewer is deliberately naive; its rule is not an Automata policy.
        for ($i = 0; $i < 3; ++$i) {
            $chosen = $route('media-review.v1');
            $id = 'reviewed-session-' . $i;
            $experience = Experience::fromStatements($id, [], new Context(['inspection' => 'media']),
                ['timestamp' => '2026-10-20T00:00:00Z'])
                ->withOutcome(new Outcome('review', $id, 'fixture-reviewer', true,
                    ['selected' => $chosen, 'requested' => 'inspect.media'],
                    ['strategy_id' => $chosen, 'strategy_version' => '1', 'context_key' => 'media-review.v1']));
            $advisor->learn($experience, 'review', new FeedbackSignal($chosen === 'inspect.media' ? 1 : -1));
        }
        // HTTP::jsonEncode has no flags for preserving float types in identities.
        $storage->contents(json_encode($advisor->checkpoint(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $storage->write();
    } else {
        $storage->read();
        $checkpoint = $storage->contents();
        if (!Arr::is($checkpoint)) { throw new RuntimeException('Checkpoint did not decode to a record.'); }
        $advisor = $advisor->restore($checkpoint);
        $router->advisor($advisor);
        foreach ($checkpoint['evidence'] as $entry) {
            $reward = $entry['reward'] === null ? null : new FeedbackSignal($entry['reward']);
            $replays += (int) !$advisor->learn(new Experience($entry['experience']), $entry['outcome_id'], $reward);
        }
    }
    echo json_encode(['pid' => getmypid(), 'before' => $before, 'after' => $route('media-review.v1'),
        'unseen' => $route('unseen-signature.v1'), 'replays_ignored' => $replays,
        'fingerprint' => $advisor->checkpoint()['fingerprint'], 'receipts' => $advisor->receipts()],
        JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
    exit(0);
}

// Separate PHP processes prevent shared in-memory objects from faking retention.
// The scratch file is private to this coordinator and removed even after failure.
$path = tempnam(sys_get_temp_dir(), 'cortex-reinforcement-');
if ($path === false) { throw new RuntimeException('Cannot create checkpoint scratch file.'); }
try {
    $runs = [];
    foreach (['learn', 'restore', 'restore'] as $phase) {
        $process = proc_open([PHP_BINARY, __FILE__, $phase, $path],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start checkpoint probe.'); }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException('Checkpoint child failed: ' . $errors); }
        $runs[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $all = static fn (callable $predicate): bool => Arr::size(Arr::make($runs)->filter($predicate)->val()) === 3;
    $checks = [
        'each_process_starts_with_configured_anchor' => $all(static fn (array $run): bool => $run['before'] === 'inspect.text'),
        'learned_action_survives_two_restarts' => $all(static fn (array $run): bool => $run['after'] === 'inspect.media'),
        'unseen_signatures_keep_initial_action' => $all(static fn (array $run): bool => $run['unseen'] === 'inspect.text'),
        'first_restart_ignores_duplicate_learning' => $runs[1]['replays_ignored'] === 3,
        'second_restart_ignores_duplicate_learning' => $runs[2]['replays_ignored'] === 3,
        'evidence_and_policy_survive_unchanged' => $all(static fn (array $run): bool => $run['fingerprint'] === $runs[0]['fingerprint']),
        'retained_receipts_agree' => $all(static fn (array $run): bool => $run['receipts'] === $runs[0]['receipts']),
    ];
    $passed = !Arr::has($checks, false, true);
    echo json_encode(['passed' => $passed, 'checks' => $checks, 'runs' => $runs,
        'scope' => 'Synthetic signed review changes real route selection across clean process restarts. This single-writer Disk fixture does not qualify atomic crash recovery, compound sensing, generalization or local-model retraining.'],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
} finally {
    unlink($path);
}
exit($passed ? 0 : 1);
