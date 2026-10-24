<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\{Arr, Str};
use BlueFission\Automata\{Context, Engine};
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{AnchoredRouteAdvisor, Experience, FileBundleStore, Outcome};
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Path\{Graph, Node};
use BlueFission\Automata\Sensory\{Input, InputSignature, Sense};
use BlueFission\Automata\Strategy\CompositeStrategy;
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouteRequest, StrategyRouter, StrategyUsage};
use BlueFission\Automata\Strategy\Workflow\{StrategyWorkflow, StrategyWorkflowRun};
use BlueFission\Automata\Support\RecordSnapshot;
use BlueFission\Behavioral\Behaviors\Event;

/** Example host: retained advice selects whole reviewed processing patterns. */
final class PatternCortex
{
    /** Keep the configured anchor independent of observed pattern rewards. */
    public static function advisor(): AnchoredRouteAdvisor
    {
        return new AnchoredRouteAdvisor([
            ['id' => 'pattern.text', 'version' => '1', 'weight' => 0.6],
            ['id' => 'pattern.fused', 'version' => '1', 'weight' => 0.5],
        ], policyId: 'pattern-fixture', minimumSamples: 2, priorWeight: 2, maximumAdjustment: 0.2);
    }

    /** Persist data-only graph proposals; executable worker bindings remain local. */
    public static function plans(): array
    {
        $plans = [];
        foreach (['text', 'fused'] as $kind) {
            $graph = new Graph();
            foreach ($kind === 'text' ? ['text'] : ['text', 'media', 'join'] as $id) {
                $graph->addNode(new Node($id, null, [], ['workflow' => [
                    'strategy_id' => $id, 'strategy_version' => '1', 'capability_id' => 'infer', 'capability_version' => '1',
                ]]));
            }
            if ($kind === 'fused') { $graph->connect('text', 'join'); $graph->connect('media', 'join'); }
            $plans[$kind] = (new StrategyWorkflow('pattern.' . $kind, '1', $graph, [$kind === 'text' ? 'text' : 'join']))->toArray();
        }
        return $plans;
    }

    /** Observe bounded synthetic sensors through actual Input, Sense and Engine. */
    public static function observe(string $text, ?array $pixels, bool $extra): array
    {
        $analysis = null;
        $input = new Input(static fn ($value) => $value);
        $input->behavior(new Event(Event::COMPLETE), static function ($event) use (&$analysis): void {
            $analysis = (new Engine())->analyze($event->context, ['segment_meta' => static function ($payload, string $type): array {
                $chunks = null;
                $sense = (new Sense())->setPreparation(static function ($value) use ($type, &$chunks): array {
                    $prepared = $type === 'video' ? Arr::make($value)->map(static fn ($pixel) => $pixel === 1 ? 'light' : 'dark')->val()
                        : Str::make($value)->lower()->split(' ')->val();
                    $chunks ??= $prepared;
                    return $prepared;
                });
                $sense->invoke($payload);
                return ['chunks' => $chunks];
            }]);
        });
        $input->scan(['segments' => [
            ['type' => 'text', 'payload' => $text],
            ...($pixels === null ? [] : [['type' => 'video', 'payload' => $pixels]]),
            ...($extra ? [['type' => 'telemetry', 'payload' => 'unseen']] : []),
        ]]);
        if (!Arr::is($analysis)) { throw new RuntimeException('Missing sensory analysis.'); }
        return $analysis;
    }

    /** Execute concurrent cooperative waves and retain evidence that joins wait. */
    public static function finish(StrategyWorkflowRun $run): array
    {
        $waves = [];
        while (($ready = $run->ready()) !== []) {
            $fibers = Arr::make($ready)->map(static fn ($id) => new Fiber(fn () => $run->execute($id)))->val();
            foreach ($fibers as $fiber) { $fiber->start(); }
            $wave = ['nodes' => $ready, 'while_waiting' => $run->ready(), 'after_first' => null];
            foreach ($fibers as $index => $fiber) {
                if ($fiber->isSuspended()) { $fiber->resume(); }
                if ($index === 0) { $wave['after_first'] = $run->ready(); }
            }
            $waves[] = $wave;
        }
        return ['result' => $run->result()->toArray(), 'waves' => $waves];
    }

    /**
     * Choose a complete processing workflow before its workers run. Its observed
     * result changes the next semantic signature; no running plan is rewritten.
     */
    public static function run(AnchoredRouteAdvisor $advisor, array $plans, string $id, string $text,
        ?array $pixels, bool $extra = false, bool $allow = true): array
    {
        $analysis = self::observe($text, $pixels, $extra);
        $signature = new InputSignature('pattern-input', '1', ['stage' => 'observed', 'types' => $analysis['gestalt']['segment_types']]);
        $calls = []; $nested = null; $adapters = [];
        // Preserve truthful authorization diagnostics in outer and nested receipts.
        $authorize = static fn ($request) => new AutonomyDecision(['allowed' => $allow,
            'code' => $allow ? AutonomyDecision::CODE_ALLOWED : AutonomyDecision::CODE_CAPABILITY_NOT_GRANTED,
            'subject_id' => $request['subject_id'], 'capability_id' => 'infer', 'capability_version' => '1']);
        foreach ($plans as $kind => $record) {
            $plan = StrategyWorkflow::fromArray($record);
            $adapters[] = new PatternWorker('pattern.' . $kind, static function ($payload) use ($plan, $authorize, $id, $signature, &$nested, &$calls): array {
                $workers = [
                    'text' => static function ($input): array {
                        $words = [];
                        foreach ($input['root']['segments'] as $segment) {
                            if ($segment['type'] === 'text') { $words = [...$words, ...$segment['meta']['chunks']]; }
                        }
                        return ['words' => $words, 'lights' => null];
                    },
                    'media' => static function ($input): int {
                        $lights = [];
                        foreach ($input['root']['segments'] as $segment) {
                            if ($segment['type'] === 'video') { $lights = [...$lights, ...$segment['meta']['chunks']]; }
                        }
                        return Arr::make($lights)->filter(static fn ($chunk) => $chunk === 'light')->size();
                    },
                    'join' => static fn ($input): array => [...$input['predecessors']['text']['output'], 'lights' => $input['predecessors']['media']['output']],
                ];
                $bindings = [];
                foreach ($workers as $name => $worker) { $bindings[] = new PatternWorker($name, $worker, $calls); }
                $nested = self::finish((new CompositeStrategy($plan, new StrategyRouter($bindings), $authorize, 'pattern-agent'))
                    ->start($payload['root'], $id . ':nested', $signature->key()));
                if ($nested['result']['status'] !== 'completed') { throw new RuntimeException('Selected pattern did not complete.'); }
                $nested['plan'] = $plan->toArray();
                $outputs = Arr::make($nested['result']['outputs'])->values()->val();
                return $outputs[0];
            }, $calls, suspend: false);
        }
        $graph = new Graph();
        $graph->addNode(new Node('process', null, [], ['workflow' => ['capability_id' => 'infer', 'capability_version' => '1',
            'candidates' => [['id' => 'pattern.text', 'version' => '1'], ['id' => 'pattern.fused', 'version' => '1']], 'selection_policy' => 'adaptive']]));
        $outer = new StrategyWorkflow('select-pattern', '1', $graph, ['process']);
        $run = (new CompositeStrategy($outer, new StrategyRouter($adapters, $advisor), $authorize, 'pattern-agent'))
            ->start($analysis, $id, $signature->key());
        $run->execute('process'); $result = $run->result()->toArray();
        $selected = $result['nodes']['process']['attempts'][0]['selected_strategy'];
        $output = $result['outputs']['process'] ?? null;
        $next = $signature->withFeatures(['stage' => 'processed', 'types' => $analysis['gestalt']['segment_types'], 'facts' => $output]);
        $experience = Experience::fromStatements($id, [], new Context(['analysis' => $analysis, 'pattern' => $nested['plan'] ?? null]),
            ['timestamp' => '2026-10-21T00:00:00Z']);
        if ($selected !== null) {
            $experience = $experience->withOutcome(new Outcome('execution', $id, 'pattern-fixture', $result['status'] === 'completed',
                ['output' => $output], ['strategy_id' => $selected['id'], 'strategy_version' => $selected['version'],
                    'context_key' => $signature->key(), 'workflow_id' => 'select-pattern', 'workflow_version' => '1', 'node_id' => 'process', 'attempt' => 1]));
        }
        return ['selected' => $selected, 'output' => $output, 'calls' => $calls, 'nested' => $nested,
            'before_signature' => $signature->key(), 'after_signature' => $next->key(), 'experience' => $experience->toArray()];
    }

    /** Score frozen raw sensor observations independently of route names or status. */
    public static function evaluate(AnchoredRouteAdvisor $advisor, array $plans): array
    {
        $rows = [];
        foreach ([[1, 0, 1, 1], [1, 1, 0, 1, 1, 1]] as $index => $pixels) {
            $run = self::run($advisor, $plans, 'heldout-' . $index, 'inspect novel ' . $index, $pixels);
            $expected = Arr::make($pixels)->filter(static fn ($pixel) => $pixel === 1)->size();
            $rows[] = ['correct' => $run['output'] === ['words' => ['inspect', 'novel', (string) $index], 'lights' => $expected], 'run' => $run];
        }
        return ['correct' => Arr::make($rows)->filter(static fn ($row) => $row['correct'])->size(), 'rows' => $rows];
    }
}

/** Reviewed host binding; a serialized pattern can select only declared workers. */
final class PatternWorker implements IStrategyRouteAdapter
{
    /** Keep the actual dispatch ledger separate from success reports. */
    public function __construct(private string $id, private Closure $worker, private array &$calls, private bool $suspend = true) {}
    /** All fixture workers are deterministic local operations. */
    public function definition(): StrategyDefinition { return new StrategyDefinition(['id' => $this->id, 'version' => '1',
        'capability_id' => 'infer', 'capability_version' => '1', 'availability' => 'available', 'side_effect_free' => true]); }
    /** Capability checks still belong to each outer and inner router. */
    public function eligibility(StrategyRouteRequest $request): StrategyEligibility { return new StrategyEligibility(['eligible' => true]); }
    /** A dispatch counts once here; nested usage is reported by the inner trace. */
    public function estimate(StrategyRouteRequest $request): StrategyUsage { return new StrategyUsage(['invocations' => 1]); }
    /** Leaf suspension exposes fan-out and join waiting without CPU parallelism. */
    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        $this->calls[] = $this->id;
        if ($this->suspend && Fiber::getCurrent()) { Fiber::suspend(); }
        return new StrategyAdapterResult(['status' => 'completed', 'output' => ($this->worker)($request->input), 'usage' => ['invocations' => 1]]);
    }
}

$phase = $argv[1] ?? 'coordinate';
if ($phase !== 'coordinate') {
    $store = new FileBundleStore($argv[2] ?? throw new InvalidArgumentException('Private directory required.'));
    $state = $store->load(); $advisor = PatternCortex::advisor(); $plans = $state['plans'] ?? PatternCortex::plans();
    $cold = PatternCortex::evaluate($advisor, $plans);
    if ($state !== null) { $advisor = $advisor->restore($state['policy']); }
    $journal = $state['journal'] ?? []; $replays = 0;
    if ($phase === 'learn') {
        foreach ([4, 6, 8] as $index => $lights) {
            $pixels = []; for ($pixel = 0; $pixel < $lights; ++$pixel) { $pixels[] = 1; }
            $run = PatternCortex::run($advisor, $plans, 'train-' . $index, 'inspect training ' . $index, $pixels);
            $reward = $run['output']['lights'] === $lights ? 1 : -1;
            $advisor->learn(new Experience($run['experience']), 'execution', new FeedbackSignal($reward));
            $journal[] = ['experience' => $run['experience'], 'reward' => $reward];
        }
        $store->compareAndSwap(null, ['plans' => $plans, 'policy' => $advisor->checkpoint(), 'journal' => $journal]);
    } elseif ($phase === 'restore') {
        foreach ($journal as $entry) { $replays += (int) !$advisor->learn(new Experience($entry['experience']), 'execution', new FeedbackSignal($entry['reward'])); }
    } else { throw new InvalidArgumentException('Unknown phase.'); }
    $after = PatternCortex::evaluate($advisor, $plans);
    $text = PatternCortex::run($advisor, $plans, 'text-control', 'inspect text', null);
    $unknown = PatternCortex::run($advisor, $plans, 'unknown-control', 'inspect unknown', [1, 1, 1], true);
    $denied = PatternCortex::run($advisor, $plans, 'denied-control', 'inspect denied', [1, 1, 1], allow: false);
    echo json_encode(['pid' => getmypid(), 'cold' => $cold, 'after' => $after, 'text' => $text, 'unknown' => $unknown,
        'denied' => $denied, 'replays' => $replays, 'plans_digest' => RecordSnapshot::fingerprint($plans),
        'policy_digest' => RecordSnapshot::fingerprint($advisor->checkpoint())], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
    exit(0);
}

// Independent PHP processes cannot share fitted objects or advisor memory.
$directory = sys_get_temp_dir() . '/cortex-pattern-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) { throw new RuntimeException('Cannot create private pattern fixture.'); }
try {
    $runs = [];
    foreach (['learn', 'restore', 'restore'] as $phase) {
        $process = proc_open([PHP_BINARY, __FILE__, $phase, $directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start pattern phase.'); }
        fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException('Pattern phase failed: ' . $error); }
        $runs[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $every = static fn (Closure $predicate): bool => Arr::make($runs)->filter($predicate)->size() === 3;
    $checks = [
        'three_distinct_processes_execute' => Arr::make($runs)->map(static fn ($run) => $run['pid'])->unique()->size() === 3,
        'fresh_anchors_miss_compound_task' => $every(static fn ($run) => $run['cold']['correct'] === 0),
        'learned_processing_pattern_improves_disjoint_frames' => $every(static fn ($run) => $run['after']['correct'] === 2),
        'different_workflow_executes_after_learning' => $every(static fn ($run) => $run['cold']['rows'][0]['run']['calls'] === ['pattern.text', 'text']
            && $run['after']['rows'][0]['run']['calls'] === ['pattern.fused', 'text', 'media', 'join']),
        'restored_pattern_keeps_parallel_join_waiting' => $every(static fn ($run) => $run['after']['rows'][0]['run']['nested']['waves'][0]
            === ['nodes' => ['text', 'media'], 'while_waiting' => [], 'after_first' => []]),
        'processed_facts_change_signature' => $every(static fn ($run) => $run['after']['rows'][0]['run']['before_signature'] !== $run['after']['rows'][0]['run']['after_signature']),
        'unrelated_text_capability_preserves_anchor' => $every(static fn ($run) => $run['text']['calls'] === ['pattern.text', 'text']
            && $run['text']['output'] === ['words' => ['inspect', 'text'], 'lights' => null]),
        'unknown_modality_does_not_borrow_learning' => $every(static fn ($run) => $run['unknown']['selected']['id'] === 'pattern.text'),
        'denial_runs_no_pattern_or_leaf' => $every(static fn ($run) => $run['denied']['calls'] === [] && $run['denied']['selected'] === null),
        'replay_does_not_readmit_reviews' => $runs[1]['replays'] === 3 && $runs[2]['replays'] === 3,
        'policy_retained_without_new_learning' => $every(static fn ($run) => $run['policy_digest'] === $runs[0]['policy_digest']),
        'frozen_patterns_retained_exactly' => $every(static fn ($run) => $run['plans_digest'] === $runs[0]['plans_digest']),
    ];
    $passed = !Arr::has($checks, false, true);
    echo json_encode(['passed' => $passed, 'checks' => $checks, 'runs' => $runs,
        'scope' => 'Synthetic retained selection of complete reviewed processing workflows, not automatic graph synthesis, CPU parallelism, general vision or local-model retraining.'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
} finally {
    // Remove only flat scratch files from this coordinator-created directory.
    foreach (glob($directory . '/*') as $file) { if (is_file($file)) { unlink($file); } }
    rmdir($directory);
}
exit($passed ? 0 : 1);
