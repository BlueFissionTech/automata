<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Data\Storage\Disk;
use BlueFission\Automata\Context;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{AnchoredRouteAdvisor, BundleLifecycle, BundleSnapshot, FileBundleStore, LearningBundle, Experience, Outcome};
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Path\{Graph, Node};
use BlueFission\Automata\Strategy\{CompositeStrategy, DecisionTreeClassification};
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouteRequest, StrategyRouter, StrategyUsage};
use BlueFission\Automata\Strategy\Workflow\StrategyWorkflow;

/** Terminate at the real publication boundary, leaving OS lock release to process exit. */
final class InterruptedBundleStore extends FileBundleStore
{
    /** Select a deterministic process-exit fault for this child only. */
    public function __construct(string $directory, private readonly string $fault = '') { parent::__construct($directory); }
    /** Exit immediately before or after the real filesystem publication. */
    protected function publish(string $prepared, string $committed): void
    {
        if ($this->fault === 'before') { exit(73); }
        parent::publish($prepared, $committed);
        if ($this->fault === 'after') { exit(74); }
    }
}

/** Fixture-owned runtime bindings; manifests never create executable authority. */
final class BundleWorker implements IStrategyRouteAdapter
{
    /** The host supplies executable code separately from the artifact manifest. */
    public function __construct(private readonly string $id, private readonly string $version, private readonly Closure $worker) {}
    /** Exact adapter versions distinguish both workflows in the retained proof. */
    public function definition(): StrategyDefinition
    {
        return new StrategyDefinition(['id' => $this->id, 'version' => $this->version,
            'capability_id' => 'infer', 'capability_version' => '1', 'mode' => $this->id === 'classify' ? 'learned' : 'deterministic',
            'side_effect_free' => true, 'availability' => 'available']);
    }
    /** Fixture bindings are locally available; router autonomy still applies. */
    public function eligibility(StrategyRouteRequest $request): StrategyEligibility { return new StrategyEligibility(['eligible' => true]); }
    /** Each fixture node executes one local invocation without provider cost. */
    public function estimate(StrategyRouteRequest $request): StrategyUsage { return new StrategyUsage(['invocations' => 1]); }
    /** Preserve the actual worker result as the next graph node's input. */
    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        return new StrategyAdapterResult(['status' => 'completed', 'output' => ($this->worker)($request->input), 'usage' => ['invocations' => 1]]);
    }
}

/** Actual graph, router and fitted-tree execution from one verified byte snapshot. */
final class BundleCortex
{
    /** Configuration stays fixed while retained feedback changes policy state. */
    public static function advisor(): AnchoredRouteAdvisor
    {
        return new AnchoredRouteAdvisor([
            ['id' => 'fallback', 'version' => '1', 'weight' => 0.6],
            ['id' => 'classify', 'version' => '1', 'weight' => 0.5],
            ['id' => 'classify', 'version' => '2', 'weight' => 0.5],
        ], policyId: 'bundle-routes', priorWeight: 2, maximumAdjustment: 0.3, minimumSamples: 3);
    }

    /** Build a versioned proposal; model and response adapter versions are explicit. */
    public static function workflow(string $version): StrategyWorkflow
    {
        $graph = new Graph();
        $graph->addNode(new Node('classify', null, [], ['workflow' => [
            'candidates' => [['id' => 'classify', 'version' => $version], ['id' => 'fallback', 'version' => '1']],
            'selection_policy' => 'adaptive', 'deterministic_preferred' => false,
            'capability_id' => 'infer', 'capability_version' => '1', 'allowed_modes' => ['learned', 'deterministic'],
        ]]));
        $graph->addNode(new Node('respond', null, [], ['workflow' => [
            'strategy_id' => 'respond', 'strategy_version' => $version,
            'capability_id' => 'infer', 'capability_version' => '1', 'allowed_modes' => ['deterministic'],
        ]]));
        $graph->connect('classify', 'respond');
        return new StrategyWorkflow('bundle.workflow', $version, $graph, ['respond']);
    }

    /** Artifact keys are allowlisted fixture names, not arbitrary paths from a manifest. */
    public static function reader(string $directory): Closure
    {
        return static function (array $component) use ($directory): string {
            $key = $component['artifact']['key'];
            if (!Arr::has(['model-1', 'model-2', 'policy-1', 'policy-2', 'workflow-1', 'workflow-2'], $key, true)) {
                throw new RuntimeException('Unregistered artifact key.');
            }
            // Integrity is over raw bytes; Disk::read() intentionally decodes JSON.
            $bytes = file_get_contents($directory . '/' . $key);
            if ($bytes === false) { throw new RuntimeException('Artifact unavailable.'); }
            return $bytes;
        };
    }

    /** Restore fitted bytes only; no training or mutable-file reread occurs here. */
    public static function run(BundleSnapshot $snapshot, float $input): array
    {
        $model = unserialize($snapshot->artifact('model'), ['allowed_classes' => [
            DecisionTreeClassification::class, \BlueFission\Automata\Strategy\Backend\ClassificationTree::class,
            \Phpml\Classification\DecisionTree\DecisionTreeLeaf::class, \Phpml\ModelManager::class,
        ]]);
        if (!$model instanceof DecisionTreeClassification) { throw new RuntimeException('Unsupported fitted artifact.'); }
        $workflow = StrategyWorkflow::fromArray(json_decode($snapshot->artifact('workflow'), true, 512, JSON_THROW_ON_ERROR));
        $policy = self::advisor()->restore(json_decode($snapshot->artifact('policy'), true, 512, JSON_THROW_ON_ERROR));
        $version = $snapshot->manifest()->identity()['version'];
        $router = new StrategyRouter([
            new BundleWorker('classify', $version, static fn ($payload) => $model->predict([$payload['root']])),
            new BundleWorker('fallback', '1', static fn () => 'wait'),
            new BundleWorker('respond', $version, static fn ($payload) => ['label' => $payload['predecessors']['classify']['output'], 'workflow_version' => $version]),
        ], $policy);
        $authorize = static fn ($request) => new AutonomyDecision(['allowed' => true, 'subject_id' => $request['subject_id'],
            'code' => AutonomyDecision::CODE_ALLOWED,
            'capability_id' => $request['capability_id'], 'capability_version' => $request['capability_version']]);
        $strategy = new CompositeStrategy($workflow, $router, $authorize, 'fixture-agent');
        $run = $strategy->start($input, 'bundle-input', 'bundle-requests.v1');
        while (($ready = $run->ready()) !== []) { $run->execute($ready[0]); }
        $result = $run->result();
        if ($result->status() !== 'completed') { throw new RuntimeException('Bundled workflow failed.'); }
        return $result->outputs()['respond'];
    }

    /** Independent frozen holdout evaluates complete runtime behavior before publication. */
    public static function evaluate(BundleSnapshot $from, BundleSnapshot $to): array
    {
        $baseline = 0; $candidate = 0; $rows = [];
        foreach ([[0.5, 'wait'], [8.5, 'assist']] as [$input, $expected]) {
            $old = self::run($from, $input); $new = self::run($to, $input);
            $baseline += (int) ($old['label'] === $expected); $candidate += (int) ($new['label'] === $expected);
            $rows[] = ['input' => $input, 'expected' => $expected, 'baseline' => $old, 'candidate' => $new];
        }
        return ['accepted' => $candidate === 2 && $candidate > $baseline, 'evidence' => ['rows' => $rows, 'baseline_correct' => $baseline, 'candidate_correct' => $candidate]];
    }

    /** Stage immutable artifacts; both bundles are fitted before any restart probe. */
    public static function prepare(string $directory): array
    {
        $bundles = [];
        $advisor = self::advisor();
        foreach (['1', '2'] as $version) {
            $model = new DecisionTreeClassification(3);
            $model->train($version === '1' ? [[0.0], [1.0]] : [[0.0], [1.0], [8.0], [9.0]],
                $version === '1' ? ['wait', 'wait'] : ['wait', 'wait', 'assist', 'assist'], 0.0);
            if ($version === '2') {
                $baseline = BundleSnapshot::resolve($bundles['1'], self::reader($directory));
                foreach ([8.1, 8.2, 8.3] as $index => $input) {
                    $actual = self::run($baseline, $input);
                    $id = 'review-' . $index;
                    $experience = Experience::fromStatements($id, [], new Context(['input' => $input]), ['timestamp' => '2026-10-20T00:00:00Z'])
                        ->withOutcome(new Outcome('human', $id, 'fixture-reviewer', true, ['actual' => $actual, 'expected' => 'assist'],
                            ['strategy_id' => 'fallback', 'strategy_version' => '1', 'context_key' => 'bundle-requests.v1', 'workflow_version' => '1']));
                    $advisor->learn($experience, 'human', new FeedbackSignal($actual['label'] === 'assist' ? 1.0 : -1.0));
                }
            }
            $bytes = ['model' => serialize($model), 'policy' => json_encode($advisor->checkpoint(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                'workflow' => json_encode(self::workflow($version)->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)];
            $components = [];
            foreach ($bytes as $slot => $content) {
                $key = $slot . '-' . $version;
                $disk = new Disk(['location' => $directory, 'name' => $key]); $disk->activate(); $disk->contents($content); $disk->write();
                $requires = [];
                if ($slot !== 'model') { $requires[] = ['slot' => 'model', 'id' => 'model', 'version' => $version, 'sha256' => hash('sha256', $bytes['model'])]; }
                if ($slot === 'workflow') { $requires[] = ['slot' => 'policy', 'id' => 'policy', 'version' => $version, 'sha256' => hash('sha256', $bytes['policy'])]; }
                $components[] = ['slot' => $slot, 'kind' => $slot, 'id' => $slot, 'version' => $version,
                    'artifact' => ['key' => $key, 'format' => $slot === 'model' ? 'trusted-phpml-tree.v1' : 'json.v1', 'sha256' => hash('sha256', $content)], 'requires' => $requires];
            }
            $bundles[$version] = new LearningBundle('cortex-fixture', $version, 'bundle-demo', $components);
            BundleSnapshot::resolve($bundles[$version], self::reader($directory));
            $disk = new Disk(['location' => $directory, 'name' => 'manifest-' . $version]); $disk->activate();
            $disk->contents(json_encode($bundles[$version]->toArray(), JSON_THROW_ON_ERROR)); $disk->write();
        }
        return $bundles;
    }
}

$phase = $argv[1] ?? 'coordinate';
if ($phase !== 'coordinate') {
    $directory = $argv[2] ?? throw new InvalidArgumentException('Private directory required.');
    $read = BundleCortex::reader($directory);
    $store = new InterruptedBundleStore($directory . '/commits', Arr::has(['before', 'after'], $phase, true) ? $phase : '');
    $owner = new BundleLifecycle($store, 'bundle-demo');
    $approve = static fn () => GovernanceDecision::approved();
    if ($phase === 'initialize') {
        $bundles = BundleCortex::prepare($directory); $owner->initialize($bundles['1'], $read);
    } elseif (Arr::has(['before', 'after', 'verify-new'], $phase, true)) {
        $manifest = LearningBundle::fromArray(json_decode(file_get_contents($directory . '/manifest-2'), true, 512, JSON_THROW_ON_ERROR));
        $never = static fn () => throw new RuntimeException('Acknowledged commit was executed again.');
        $owner->activate('promote', 0, $manifest, $phase === 'verify-new' ? $never : BundleCortex::evaluate(...), $phase === 'verify-new' ? $never : $approve, $phase === 'verify-new' ? $never : $read);
    } elseif ($phase === 'rollback') {
        $owner->rollback('rollback', 1, $approve, $read);
    } elseif (!Arr::has(['verify-old', 'verify-rollback'], $phase, true)) { throw new InvalidArgumentException('Unknown probe phase.'); }
    $active = $owner->active($read);
    echo json_encode(['pid' => getmypid(), 'revision' => $owner->revision(), 'bundle' => $active->manifest()->identity(),
        'normal' => BundleCortex::run($active, 0.5), 'urgent' => BundleCortex::run($active, 8.5), 'receipts' => $owner->receipts()], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
    exit(0);
}

$directory = sys_get_temp_dir() . '/cortex-bundle-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700) || !mkdir($directory . '/commits', 0700)) { throw new RuntimeException('Cannot create private bundle probe.'); }
try {
    $runs = []; $interrupted = [];
    foreach (['initialize', 'before', 'verify-old', 'after', 'verify-new', 'rollback', 'verify-rollback'] as $phase) {
        $process = proc_open([PHP_BINARY, __FILE__, $phase, $directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start bundle probe.'); }
        fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process);
        if (Arr::has(['before', 'after'], $phase, true)) {
            $expected = $phase === 'before' ? 73 : 74;
            if ($exit !== $expected) { throw new RuntimeException('Fault probe failed before reaching publication: ' . $error); }
            $interrupted[$phase] = true; continue;
        }
        if ($exit !== 0) { throw new RuntimeException('Bundle child failed: ' . $error); }
        $runs[$phase] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $checks = [
        'baseline_retained' => $runs['initialize']['urgent']['label'] === 'wait',
        'interrupted_before_publication' => $interrupted['before'],
        'prepared_record_not_active' => $runs['verify-old']['revision'] === 0 && $runs['verify-old']['bundle']['version'] === '1',
        'no_receipt_for_uncommitted_attempt' => $runs['verify-old']['receipts'] === [],
        'interrupted_after_publication' => $interrupted['after'],
        'lost_ack_does_not_repeat_activation' => $runs['verify-new']['revision'] === 1 && Arr::size($runs['verify-new']['receipts']) === 1,
        'learned_model_policy_and_workflow_active' => $runs['verify-new']['urgent'] === ['label' => 'assist', 'workflow_version' => '2'],
        'anchor_capability_preserved' => $runs['verify-new']['normal']['label'] === 'wait',
        'held_out_whole_bundle_improves' => $runs['verify-new']['receipts'][0]['evaluation']['evidence']['baseline_correct'] === 1 && $runs['verify-new']['receipts'][0]['evaluation']['evidence']['candidate_correct'] === 2,
        'rollback_restores_complete_bundle' => $runs['rollback']['urgent'] === ['label' => 'wait', 'workflow_version' => '1'],
        'rollback_survives_restart' => $runs['verify-rollback']['revision'] === 2 && $runs['verify-rollback']['urgent'] === $runs['rollback']['urgent'],
        'activation_history_retained' => Arr::size($runs['verify-rollback']['receipts']) === 2,
    ];
    $passed = !Arr::has($checks, false, true);
    echo json_encode(['passed' => $passed, 'checks' => $checks, 'runs' => $runs,
        'scope' => 'Local process-crash qualification with immutable artifacts and single-writer publication. Actual routed graph and fitted tree; synthetic features/rewards, no distributed leases, power-loss, hostile directory rollback or generalization claim.'],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
} finally {
    // Only files in the two exact private directories created by this probe are removed.
    foreach (glob($directory . '/commits/*') as $path) { if (is_file($path)) { unlink($path); } }
    rmdir($directory . '/commits');
    foreach (glob($directory . '/*') as $path) { if (is_file($path)) { unlink($path); } }
    rmdir($directory);
}
exit($passed ? 0 : 1);
