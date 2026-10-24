<?php

namespace BlueFission\Examples\Cortex;

use BlueFission\{Arr, Num, Str};
use BlueFission\Automata\{Context, Engine};
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{AnchoredRouteAdvisor, Experience, Outcome};
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Path\{Graph, Node};
use BlueFission\Automata\Sensory\{Input, InputSignature, Sense};
use BlueFission\Automata\Strategy\CompositeStrategy;
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouteRequest, StrategyRouter, StrategyUsage};
use BlueFission\Automata\Strategy\Workflow\StrategyWorkflow;
use BlueFission\Automata\Support\RecordSnapshot;
use BlueFission\Behavioral\Behaviors\Event;
use InvalidArgumentException;
use RuntimeException;

/**
 * Example-owned, naive compound agent assembled from actual library components.
 * This fixture's sensor formats, feature buckets and graph policies are not an
 * Automata modality catalog or an automatic promotion/authorization policy.
 */
final class CompoundCortex
{
    private Engine $engine;
    private AnchoredRouteAdvisor $advisor;
    private int $sensoryCompletions = 0;
    private array $calls = [];
    private readonly ?array $modelIdentity;

    /**
     * Anchor reviewed routes; an optional host-bound model supplies careful intent.
     * The callback remains executable host code, never a deserialized policy.
     */
    public function __construct(private readonly ?\Closure $responseIntent = null, ?array $modelIdentity = null)
    {
        $this->modelIdentity = $modelIdentity === null ? null : RecordSnapshot::copy($modelIdentity);
        if ($modelIdentity !== null) {
            RecordSnapshot::identifier($modelIdentity['id'] ?? null, 'model id');
            RecordSnapshot::identifier($modelIdentity['version'] ?? null, 'model version');
        }
        $this->engine = new Engine();
        $this->advisor = new AnchoredRouteAdvisor([
            ['id' => 'response.quick', 'version' => '1', 'weight' => 0.6],
            ['id' => 'response.careful', 'version' => '1', 'weight' => 0.5],
        ], policyId: 'compound-fixture', minimumSamples: 2, priorWeight: 2, maximumAdjustment: 0.2);
    }

    /**
     * Observe, categorize, append relevant branches, join and route a response.
     * Each pass freezes a separate proposal and signature. Prior plans/results
     * stay readable; in-flight topology is never mutated behind an executor.
     */
    public function run(string $session, array $raw, bool $allowResponse = true): array
    {
        $this->sensoryCompletions = 0;
        $this->calls = [];
        $analysis = null;
        $input = new Input(self::normalize(...));
        $input->behavior(new Event(Event::COMPLETE), function ($event) use (&$analysis): void {
            $analysis = $this->engine->analyze($event->context, ['segment_meta' => function ($payload, string $type): array {
                return ['sense' => $this->observe($payload, $type)];
            }]);
        });
        $input->scan($raw);
        if (!Arr::is($analysis)) { throw new RuntimeException('Input did not complete compound analysis.'); }
        $ingress = $analysis;
        $passes = [];
        $signature = $this->signature($analysis, 'ingress', []);
        $graph = new Graph();
        $workers = [];
        foreach ($analysis['segments'] as $index => $segment) {
            $id = 'sense' . $index;
            self::node($graph, $id);
            $workers[$id] = static fn () => ['type' => $segment['type'], 'chunks' => $segment['meta']['sense']['chunks']];
        }
        self::node($graph, 'categorize');
        foreach (Arr::make($workers)->keys()->val() as $id) { $graph->connect($id, 'categorize'); }
        $workers['categorize'] = static function (array $input): array {
            $urgent = false;
            $visible = null;
            foreach ($input['predecessors'] as $receipt) {
                $observation = $receipt['output'];
                if ($observation['type'] === 'text') { $urgent = $urgent || Arr::has($observation['chunks'], 'help', true); }
                if ($observation['type'] === 'video') {
                    $lights = Arr::make($observation['chunks'])->filter(static fn ($pixel) => $pixel === 'light')->size();
                    $visible = ($visible ?? false) || $lights >= 3;
                }
            }
            return ['urgent' => $urgent, 'visible' => $visible];
        };
        $passes[] = $this->execute($session, 'categorize', $graph, ['categorize'], $workers, $analysis, [], $signature);
        $facts = $passes[0]['result']['outputs']['categorize'];

        // Re-analyze with the new observations. The original sensor capture is
        // retained, while the interpretation and next graph are recomposed.
        $analysis = $this->reconsider($analysis, 'categorized', $facts);
        $signature = $this->signature($analysis, 'categorized', $facts);
        $graph = new Graph();
        self::node($graph, 'dispatch');
        self::node($graph, 'compose');
        $workers = ['dispatch' => static fn ($input) => $input['root']['facts']];
        $branches = [];
        foreach (['text.sentiment' => ['urgent', 'urgent-request'], 'media.marker' => ['visible', 'bright-marker']] as $id => [$field, $value]) {
            if (($facts[$field] ?? null) !== true) { continue; }
            self::node($graph, $id);
            $graph->connect('dispatch', $id, ['when' => ['path' => [$field], 'equals' => true]]);
            $graph->connect($id, 'compose');
            $workers[$id] = static fn () => $value;
            $branches[] = $id;
        }
        if ($branches === []) { $graph->connect('dispatch', 'compose'); }
        $workers['compose'] = static fn (array $input): array => [...$input['root']['facts'],
            'details' => Arr::make($input['predecessors'])->map(static fn (array $receipt) => $receipt['output'])->val()];
        $passes[] = $this->execute($session, 'enrich', $graph, ['compose'], $workers, $analysis, $facts, $signature);
        $facts = $passes[1]['result']['outputs']['compose'];

        $analysis = $this->reconsider($analysis, 'enriched', $facts);
        $signature = $this->signature($analysis, 'response', $facts);
        $graph = new Graph();
        $graph->addNode(new Node('answer', null, [], ['workflow' => [
            'capability_id' => 'infer', 'capability_version' => '1',
            'candidates' => [['id' => 'response.quick', 'version' => '1'], ['id' => 'response.careful', 'version' => '1']],
            'selection_policy' => 'adaptive',
        ]]));
        $workers = [
            'response.quick' => static fn ($input) => ['intent' => $input['root']['facts']['urgent'] ? 'assist' : 'observe', 'media_confirmed' => false],
            'response.careful' => fn ($input) => ['intent' => $this->responseIntent === null
                ? ($input['root']['facts']['urgent'] ? 'assist' : 'observe')
                : ($this->responseIntent)(self::features($input['root']['analysis'])),
                'media_confirmed' => $input['root']['facts']['visible'] === true],
        ];
        $passes[] = $this->execute($session, 'response', $graph, ['answer'], $workers, $analysis, $facts, $signature, $allowResponse);
        $last = $passes[2]['result'];
        $selected = $last['nodes']['answer']['attempts'][0]['selected_strategy'];
        $output = $last['outputs']['answer'] ?? null;
        $experience = Experience::fromStatements($session, [], new Context(['input' => $raw, 'signature' => $signature->toArray(), 'facts' => $facts,
            'model_features' => self::features($ingress), 'feature_schema' => ['id' => 'compound-light-count', 'version' => '1'],
            'processing_patterns' => Arr::make($passes)->map(static fn ($pass) => $pass['plan'])->val()]),
            ['timestamp' => '2026-10-20T00:00:00Z', 'trace_id' => $session]);
        if ($selected !== null) {
            $experience = $experience->withOutcome(new Outcome('execution', $session, 'compound-fixture', $last['status'] === 'completed',
                ['output' => $output], ['strategy_id' => $selected['id'], 'strategy_version' => $selected['version'], 'context_key' => $signature->key(),
                    'workflow_id' => $passes[2]['plan']['id'], 'workflow_version' => $passes[2]['plan']['version'],
                    'node_id' => 'answer', 'attempt' => 1, 'task_id' => $session . ':response',
                    'model' => $selected['id'] === 'response.careful' ? $this->modelIdentity : null]));
        }
        return ['analysis' => $ingress, 'passes' => $passes, 'selected' => $selected, 'output' => $output,
            'context_key' => $signature->key(), 'experience' => $experience, 'sensory_completions' => $this->sensoryCompletions,
            'response_calls' => Arr::make($this->calls)->filter(static fn ($id) => Str::make($id)->startsWith('response.'))->size()];
    }

    /** Admit explicit fixture review only after the caller has evaluated the action. */
    public function feedback(array $session, ?FeedbackSignal $signal): bool
    {
        return $this->advisor->learn($session['experience'], 'execution', $signal);
    }

    /** Export the general advisor checkpoint; this fixture does not own durable storage. */
    public function checkpoint(): array { return $this->advisor->checkpoint(); }

    /** Naive fixture feature derived from actual Sense chunks, independent of labels. */
    public static function features(array $analysis): array
    {
        $lights = 0;
        foreach ($analysis['segments'] as $segment) {
            if ($segment['type'] !== 'video') { continue; }
            $count = Arr::make($segment['meta']['sense']['chunks'])->filter(static fn ($chunk) => $chunk === 'light')->size();
            $lights = Num::make($lights)->add($count)->val();
        }
        return [(float) $lights];
    }

    /** Activate an independently reconstructed policy in this example-owned agent. */
    public function restore(array $checkpoint): self
    {
        $this->advisor = $this->advisor->restore($checkpoint);
        return $this;
    }

    /** Normalize bounded fixture input without coercing unknown values into text. */
    private static function normalize(array $input): array
    {
        $input = RecordSnapshot::copy($input);
        if (!Arr::is($input['segments'] ?? null) || !Arr::check($input['segments'], 'array_is_list')
            || $input['segments'] === [] || Arr::size($input['segments']) > 8) {
            throw new InvalidArgumentException('This fixture accepts one to eight sensor segments.');
        }
        return ['segments' => Arr::make($input['segments'])->map(static function (array $segment): array {
            $type = RecordSnapshot::identifier($segment['type'] ?? null, 'sensor type');
            if (!Arr::hasKey($segment, 'payload')) { throw new InvalidArgumentException('Sensor payload required.'); }
            if ($type === 'text') {
                if (!Str::is($segment['payload'])) { throw new InvalidArgumentException('Text sensor requires text.'); }
                $segment['payload'] = Str::make($segment['payload'])->trim()->lower()->replacePattern('/\s+/', ' ')->val();
            }
            if ($type === 'video' && (!Arr::is($segment['payload']) || !Arr::check($segment['payload'], 'array_is_list')
                || Arr::make($segment['payload'])->filter(static fn ($value) => $value !== 0 && $value !== 1)->size() !== 0)) {
                throw new InvalidArgumentException('Video fixture requires a list of binary pixels.');
            }
            $segment['meta'] = ['mime' => RecordSnapshot::identifier($segment['meta']['mime'] ?? null, 'MIME type')];
            return $segment;
        })->val()];
    }

    /** Decode fixture chunks through real Sense preparation and verify its completion. */
    private function observe(mixed $payload, string $type): array
    {
        $chunks = null;
        $completed = null;
        $sense = (new Sense())->setPreparation(static function ($value) use ($type, &$chunks): array {
            $prepared = Arr::is($value) && $type === 'video'
                ? Arr::make($value)->map(static fn ($pixel) => $pixel === 1 ? 'light' : 'dark')->val()
                : ($type === 'text' || Str::is($value) ? Str::make((string) $value)->split(' ')->val() : ['unrecognized-sensor']);
            $chunks ??= $prepared;
            return $prepared;
        });
        $sense->behavior(new Event(Event::COMPLETE), static function ($event) use (&$completed): void { $completed = $event->context; });
        $observation = $sense->invoke($payload);
        if ($completed !== $observation) { throw new RuntimeException('Sense completion did not preserve the observation.'); }
        ++$this->sensoryCompletions;
        return ['chunks' => $chunks, 'statistics' => $observation, 'attention' => $sense->attentionScore(), 'confidence' => null];
    }

    /** Re-run Engine segmentation/aggregation with the newly completed pass facts. */
    private function reconsider(array $analysis, string $stage, array $facts): array
    {
        return $this->engine->analyze(['segments' => Arr::make($analysis['segments'])->map(static fn ($segment) => [
            ...$segment, 'meta' => [...$segment['meta'], 'stage' => $stage, 'facts' => $facts],
        ])->val()]);
    }

    /** Coarse, versioned features share experience without memorizing raw session ids. */
    private function signature(array $analysis, string $stage, array $facts): InputSignature
    {
        $mimes = $sizes = [];
        foreach ($analysis['segments'] as $segment) {
            $mime = $segment['meta']['mime'];
            $mimes[$mime] = (int) Num::make($mimes[$mime] ?? 0)->add(1)->val();
            $size = Str::make(json_encode($segment['payload'], JSON_THROW_ON_ERROR))->len() <= 128 ? 'compact' : 'extended';
            $sizes[$size] = (int) Num::make($sizes[$size] ?? 0)->add(1)->val();
        }
        return new InputSignature('compound-fixture-features', '1', ['stage' => $stage,
            'types' => $analysis['gestalt']['segment_types'], 'mimes' => $mimes, 'sizes' => $sizes, 'facts' => $facts]);
    }

    /** Each generated node declares an exact strategy and the same fixture capability. */
    private static function node(Graph $graph, string $id): void
    {
        $graph->addNode(new Node($id, null, [], ['workflow' => ['strategy_id' => $id, 'strategy_version' => '1',
            'capability_id' => 'infer', 'capability_version' => '1']]));
    }

    /** Execute a frozen proposal with fresh authorization and attributable receipts. */
    private function execute(string $session, string $stage, Graph $graph, array $outputs, array $workers,
        array $analysis, array $facts, InputSignature $signature, bool $allow = true): array
    {
        $proposal = (new StrategyWorkflow('compound.' . $stage, '1', $graph, $outputs))->toArray();
        // The builder has a stable revision, but different graph declarations
        // must not masquerade as the same exact immutable workflow version.
        $proposal['version'] = '1.' . RecordSnapshot::fingerprint($proposal);
        $plan = StrategyWorkflow::fromArray($proposal);
        $adapters = [];
        foreach ($workers as $id => $worker) {
            $adapters[] = new CompoundWorker($id, $worker, $this->calls);
        }
        $router = new StrategyRouter($adapters, $this->advisor);
        $authorize = static fn (array $request) => new AutonomyDecision(['allowed' => $allow,
            'subject_id' => $request['subject_id'], 'capability_id' => $request['capability_id'], 'capability_version' => $request['capability_version']]);
        $run = (new CompositeStrategy($plan, $router, $authorize, 'fixture-agent'))
            ->start(['analysis' => $analysis, 'facts' => $facts], $session . ':' . $stage, $signature->key());
        $schedule = [];
        while (($ready = $run->ready()) !== []) {
            // Cooperative waves make waiting observable: every ready worker may
            // suspend before a join sees any result. This is not CPU parallelism.
            $fibers = Arr::make($ready)->map(static fn (string $id) => new \Fiber(fn () => $run->execute($id)))->val();
            foreach ($fibers as $fiber) { $fiber->start(); }
            $wave = ['nodes' => $ready, 'ready_while_waiting' => $run->ready(), 'ready_after_first' => null];
            foreach ($fibers as $index => $fiber) {
                if ($fiber->isSuspended()) { $fiber->resume(); }
                if ($index === 0) { $wave['ready_after_first'] = $run->ready(); }
            }
            $schedule[] = $wave;
        }
        return ['signature' => ['key' => $signature->key(), 'record' => $signature->toArray()],
            'plan' => $plan->toArray(), 'result' => $run->result()->toArray(), 'schedule' => $schedule];
    }
}

/** Host-bound fixture callback; no serialized callback or policy becomes executable. */
final class CompoundWorker implements IStrategyRouteAdapter
{
    /** Share only the local call ledger for denial assertions. */
    public function __construct(private string $id, private $worker, private array &$calls) {}
    /** All fixture actions are deterministic, available and side-effect-free. */
    public function definition(): StrategyDefinition { return new StrategyDefinition(['id' => $this->id, 'version' => '1',
        'capability_id' => 'infer', 'capability_version' => '1', 'availability' => 'available', 'side_effect_free' => true]); }
    /** The fixture adds no restrictions beyond the router and host authorization. */
    public function eligibility(StrategyRouteRequest $request): StrategyEligibility { return new StrategyEligibility(['eligible' => true]); }
    /** One callback is one invocation; no provider billing is measured. */
    public function estimate(StrategyRouteRequest $request): StrategyUsage { return new StrategyUsage(['invocations' => 1]); }
    /** Record the actual dispatched identity independently of the returned output. */
    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        $this->calls[] = $this->id;
        if (\Fiber::getCurrent()) { \Fiber::suspend('waiting:' . $this->id); }
        return new StrategyAdapterResult(['status' => 'completed', 'output' => ($this->worker)($request->input), 'usage' => ['invocations' => 1]]);
    }
}
