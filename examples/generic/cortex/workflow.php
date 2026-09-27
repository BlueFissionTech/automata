<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\Path\{Graph, Node};
use BlueFission\Automata\Strategy\{CompositeStrategy, NaiveBayesTextClassification};
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouteRequest, StrategyRouter, StrategyUsage};
use BlueFission\Automata\Strategy\Workflow\StrategyWorkflow;

['training' => $rows] = require __DIR__ . '/fixtures.php';

// Train an actual local classifier so the graph composes a prediction, not a
// hard-coded answer. The two other workers remain deterministic fixture code.
$classifier = new NaiveBayesTextClassification();
$trainingInputs = Arr::make($rows)->map(static fn ($row) => $row[0])->val();
$trainingLabels = Arr::make($rows)->map(static fn ($row) => $row[1])->val();
$classifier->getPipeline()->train($trainingInputs, $trainingLabels);

// Declare exact identities and dependencies before a run exists. The join
// receives both terminal predecessors; the plan itself grants no authority.
$graph = new Graph();
foreach (['intent', 'context', 'answer'] as $id) {
    $graph->addNode(new Node($id, null, [], [
        'workflow' => [
            'strategy_id' => $id,
            'strategy_version' => '1',
            'capability_id' => 'infer',
            'capability_version' => '1',
            'allowed_modes' => [$id === 'intent' ? 'learned' : 'deterministic'],
        ],
    ]));
}
$graph->connect('intent', 'answer');
$graph->connect('context', 'answer');
$plan = new StrategyWorkflow('concierge.workflow', '1', $graph, ['answer']);

// Fibers model cooperative host scheduling: both independent workers suspend
// before either completes. This is overlap, not CPU parallelism or durability.
$workers = [
    'intent' => static function ($input) use ($classifier) {
        if (Fiber::getCurrent()) { Fiber::suspend('intent waiting'); }
        return $classifier->predict($input['root']);
    },
    'context' => static function () {
        if (Fiber::getCurrent()) { Fiber::suspend('context waiting'); }
        return 'fixture-only';
    },
    'answer' => static fn ($input) => [
        'intent' => $input['predecessors']['intent']['output'],
        'context' => $input['predecessors']['context']['output'],
    ],
];

// The router owns the adapter identity and result contract. Keep a separate
// call ledger so denial can prove that no worker was invoked.
$calls = [];
$adapters = [];
foreach ($workers as $id => $worker) {
    $adapters[] = new class($id, $worker, $calls) implements IStrategyRouteAdapter {
        public function __construct(private string $id, private $worker, private array &$calls)
        {
        }

        public function definition(): StrategyDefinition
        {
            return new StrategyDefinition([
                'id' => $this->id,
                'version' => '1',
                'capability_id' => 'infer',
                'capability_version' => '1',
                'mode' => $this->id === 'intent' ? 'learned' : 'deterministic',
                'side_effect_free' => true,
                'availability' => 'available',
            ]);
        }

        public function eligibility(StrategyRouteRequest $request): StrategyEligibility
        {
            return new StrategyEligibility(['eligible' => true]);
        }

        public function estimate(StrategyRouteRequest $request): StrategyUsage
        {
            return new StrategyUsage(['invocations' => 1]);
        }

        public function execute(StrategyRouteRequest $request): StrategyAdapterResult
        {
            $this->calls[] = ['node' => $this->id, 'request_id' => $request->id];
            return new StrategyAdapterResult([
                'status' => 'completed',
                'code' => 'completed',
                'output' => ($this->worker)($request->input),
            ]);
        }
    };
}

$router = new StrategyRouter($adapters);

// A host decision is refreshed per node. This fixture grants only this exact
// subject/capability pair; a plan or prediction never authorizes an effect.
$approve = static fn (array $request) => new AutonomyDecision([
    'allowed' => true,
    'subject_id' => $request['subject_id'],
    'capability_id' => $request['capability_id'],
    'capability_version' => $request['capability_version'],
]);
$trace = new TaskTrace('workflow-proof');
$strategy = new CompositeStrategy($plan, $router, $approve, 'fixture-actor', trace: $trace);

// Start both independent workers, then prove the answer join stays blocked
// until each has supplied a terminal receipt.
$run = $strategy->start('confirm booking', 'overlap');
$intent = new Fiber(fn () => $run->execute('intent'));
$context = new Fiber(fn () => $run->execute('context'));
$checks = [];
$checks['independent_workers_overlap'] = $intent->start() === 'intent waiting'
    && $context->start() === 'context waiting' && $intent->isSuspended() && $context->isSuspended();
$checks['join_waits_for_both'] = $run->ready() === [];
$context->resume();
$checks['one_result_does_not_unlock_join'] = $run->ready() === [];
$intent->resume();
$checks['both_results_unlock_join'] = $run->ready() === ['answer'];
$run->execute('answer');
$checks['real_classifier_output_composes'] = $run->result()->outputs() === [
    'answer' => ['intent' => 'checkin', 'context' => 'fixture-only'],
];
$checks['route_identity_preserved'] = $run->result()->toArray()['workflow'] === ['id' => 'concierge.workflow', 'version' => '1'];
$checks['unknown_metrics_stay_unknown'] = $run->result()->toArray()['cost'] === null
    && $run->result()->toArray()['confidence'] === null;

// Export/import rebuilds a proposal, not live authorization or worker state.
$roundTrip = StrategyWorkflow::fromArray(json_decode(json_encode($plan->toArray(), JSON_THROW_ON_ERROR), true));
$checks['plan_round_trip'] = $roundTrip->toArray() === $plan->toArray();

// CompositeStrategy uses the ordinary Intelligence selection path.
$intelligence = new Intelligence();
$intelligence->registerStrategy($strategy, 'workflow');
$checks['ordinary_intelligence_selects_composite'] = $intelligence->predict('confirm booking') === $run->result()->outputs();

// The negative control denies every node; no adapter call may occur.
$denied = (new CompositeStrategy($plan, $router, static fn () => new AutonomyDecision(), 'fixture-actor'))->start('confirm booking');
$before = Arr::size($calls);
$denied->execute('intent');
$denied->execute('context');
$checks['denied_nodes_never_execute'] = Arr::size($calls) === $before
    && $denied->result()->status() === 'exhausted';

// Cancellation stops new dispatch but preserves a late in-flight receipt.
$cancelled = $strategy->start('confirm booking', 'cancelled');
$pending = new Fiber(fn () => $cancelled->execute('intent'));
$pending->start();
$cancelled->cancel();
$pending->resume();
$checks['cancel_preserves_inflight_receipt'] = $cancelled->result()->status() === 'cancelled'
    && $cancelled->result()->toArray()['nodes']['intent']['status'] === 'completed'
    && $cancelled->ready() === [];
$checks['task_trace_records_node_routes'] = Arr::size($trace->toArray()['spans']) >= 3;

$passed = !Arr::has($checks, false, true);

// Emit inspectable evidence and make every limit explicit for consumers.
echo json_encode([
    'experiment' => 'cortex-workflow-v1',
    'passed' => $passed,
    'checks' => $checks,
    'result' => $run->result()->toArray(),
    'calls' => $calls,
    'trace' => $trace->toArray(),
    'limits' => [
        'Cooperative Fiber overlap in one process, not CPU parallelism or durable workers.',
        'Synthetic policy and context; no providers, costs or operational effects.',
        'Host owns scheduling, current authorization, model state and resource enforcement.',
    ],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
