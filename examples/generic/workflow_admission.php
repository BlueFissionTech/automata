<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Path\{Graph, Node};
use BlueFission\Automata\Strategy\CompositeStrategy;
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouteRequest, StrategyRouter, StrategyUsage};
use BlueFission\Automata\Strategy\Workflow\{StrategyWorkflow, WorkflowAdmissionPolicy};

// The fixtures perform no external I/O. Fiber checkpoints represent reads
// waiting for host readiness, not CPU parallelism or a production event loop.
$graph = new Graph();
$calls = [];
$authorizations = [];
$adapters = [];
foreach (['a', 'b', 'c', 'join'] as $id) {
    $graph->addNode(new Node($id, null, [], ['workflow' => [
        'strategy_id' => $id, 'strategy_version' => '1', 'capability_id' => 'read', 'capability_version' => '1',
    ]]));
    $adapters[] = new class($id, $calls) implements IStrategyRouteAdapter {
        public function __construct(private string $id, private array &$calls) {}
        public function definition(): StrategyDefinition { return new StrategyDefinition([
            'id' => $this->id, 'version' => '1', 'capability_id' => 'read', 'capability_version' => '1',
            'mode' => 'deterministic', 'availability' => 'available', 'side_effect_free' => true,
        ]); }
        public function eligibility(StrategyRouteRequest $request): StrategyEligibility { return new StrategyEligibility(['eligible' => true]); }
        public function estimate(StrategyRouteRequest $request): StrategyUsage { return new StrategyUsage(['invocations' => 1]); }
        public function execute(StrategyRouteRequest $request): StrategyAdapterResult {
            $this->calls[] = $this->id;
            if ($this->id === 'b') { Fiber::suspend('read waiting'); }
            return new StrategyAdapterResult(['status' => 'completed', 'code' => 'completed',
                'output' => $this->id === 'join'
                    ? Arr::make($request->input['predecessors'])->map(static fn ($parent) => $parent['output'])->val()
                    : $this->id]);
        }
    };
}
foreach (['a', 'b', 'c'] as $id) { $graph->connect($id, 'join', []); }
$authorize = static function (array $request) use (&$authorizations): AutonomyDecision {
    $authorizations[] = $request['metadata']['node_id'];
    if ($request['metadata']['node_id'] === 'a') { Fiber::suspend('authorization waiting'); }
    return new AutonomyDecision(['allowed' => true, 'subject_id' => $request['subject_id'],
        'capability_id' => $request['capability_id'], 'capability_version' => $request['capability_version']]);
};
$strategy = new CompositeStrategy(new StrategyWorkflow('bounded.reads', '1', $graph, ['join']),
    new StrategyRouter($adapters), $authorize, 'fixture');
$now = 0;
$clock = static function () use (&$now): int { return $now; };
$run = $strategy->startAdmitted(['request' => 'fixture'], new WorkflowAdmissionPolicy(2), $clock, 10, 'bounded');
$a = new Fiber(fn () => $run->execute('a'));
$b = new Fiber(fn () => $run->execute('b'));
$checks = [];
$checks['authorization_and_read_overlap'] = $a->start() === 'authorization waiting' && $b->start() === 'read waiting';
$checks['both_occupy_capacity'] = $run->result()->toArray()['admission']['in_flight'] === 2;
$run->execute('c');
$blocked = $run->result()->toArray();
$checks['direct_execute_cannot_bypass_capacity'] = $blocked['nodes']['c']['status'] === 'pending'
    && $blocked['nodes']['c']['attempts'] === [] && $authorizations === ['a', 'b'];
$checks['scheduled_is_not_invoked'] = $blocked['admission']['scheduling_attempts'] === 2 && $blocked['admission']['adapter_invocations'] === 1;
$b->resume();
$run->execute('c');
$checks['join_still_waits'] = $run->ready() === [];
$a->resume();
$checks['join_ready_after_required_terminals'] = $run->ready() === ['join'];
$run->execute('join');
$checks['declared_output_order'] = $run->result()->outputs() === ['join' => ['a' => 'a', 'b' => 'b', 'c' => 'c']];
$checks['settled_releases_all_capacity'] = $run->result()->toArray()['settled'] && $run->result()->toArray()['admission']['in_flight'] === 0;

// Expiry is tested at equality while authorization is suspended. The existing
// Fiber resumes to a not-invoked receipt; no new adapter receives the request.
$expired = $strategy->startAdmitted(null, new WorkflowAdmissionPolicy(1), $clock, 10, 'expired');
$worker = new Fiber(fn () => $expired->execute('a'));
$worker->start(); $before = Arr::size($calls); $now = 10; $worker->resume();
$checks['deadline_equality_prevents_invocation'] = Arr::size($calls) === $before
    && $expired->result()->status() === 'expired' && !$expired->result()->toArray()['nodes']['a']['attempts'][0]['executed'];
$passed = !Arr::make($checks)->has(false, true);
echo json_encode(['passed' => $passed, 'checks' => $checks, 'run' => $run->result()->toArray(), 'expired' => $expired->result()->toArray(),
    'limits' => ['Cooperative pure-read fixtures only; host owns I/O readiness and resumption.', 'No hard interruption, external effects, distributed workers or provider calls.']], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
exit($passed ? 0 : 1);
