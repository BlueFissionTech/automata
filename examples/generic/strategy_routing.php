<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\DepthFirstTraceMethod;
use BlueFission\Automata\DecisionTree\Node;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyPacket;
use BlueFission\Automata\LLM\Agent\Capability\CapabilityDefinition;
use BlueFission\Automata\LLM\Agent\Capability\CapabilityRegistry;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\Strategy\Routing\Adapter\DecisionTreeRouteAdapter;
use BlueFission\Automata\Strategy\Routing\StrategyDefinition;
use BlueFission\Automata\Strategy\Routing\StrategyEligibility;
use BlueFission\Automata\Strategy\Routing\StrategyRouteRequest;
use BlueFission\Automata\Strategy\Routing\StrategyRouter;
use BlueFission\Automata\Strategy\Routing\StrategyUsage;

$capabilities = new CapabilityRegistry([[
    'id' => 'policy.route',
    'version' => '1.0',
    'owner' => 'automata',
    'description' => 'Route a bounded, side-effect-free policy decision.',
    'availability' => CapabilityDefinition::AVAILABILITY_AVAILABLE,
]]);
$packet = new AutonomyPacket([
    'id' => 'autonomy-strategy-example',
    'subject_id' => 'dispatch-agent',
    'requested_capabilities' => [['id' => 'policy.route', 'version' => '1.0']],
    'grants' => [[
        'capability_id' => 'policy.route',
        'capability_version' => '1.0',
        'subject_id' => 'dispatch-agent',
        'granted' => true,
        'limits' => ['max_invocations' => 1],
        'evidence' => [['source' => 'operator-approval']],
    ]],
    'approval_state' => AutonomyPacket::APPROVAL_APPROVED,
    'trace_id' => 'trace-strategy-example',
]);
$authorization = $packet->authorize('policy.route', '1.0', $capabilities);

$score = static fn (array $value, array $children, array $state): float =>
    (float)($value['base_score'] ?? 0)
    + (($value['decision'] ?? '') === ($state['preferred'] ?? '') ? 10.0 : 0.0);
$root = new Node(['id' => 'root', 'decision' => 'review', 'base_score' => 0], $score);
$ground = new Node(['id' => 'ground', 'decision' => 'ground_dispatch', 'base_score' => 2], $score);
$air = new Node(['id' => 'air', 'decision' => 'airlift_review', 'base_score' => 1], $score);
$root->addChild($ground);
$root->addChild($air);
$tree = new DecisionTree();
$tree->setRoot($root);

$adapter = new DecisionTreeRouteAdapter(
    $tree,
    new DepthFirstTraceMethod(),
    new StrategyDefinition([
        'id' => 'tree.dispatch',
        'version' => '1.0',
        'capability_id' => 'policy.route',
        'capability_version' => '1.0',
        'family' => 'decision_tree',
        'mode' => StrategyDefinition::MODE_DETERMINISTIC,
        'side_effect_free' => true,
        'availability' => StrategyDefinition::AVAILABILITY_AVAILABLE,
    ]),
    new StrategyEligibility([
        'eligible' => true,
        'code' => StrategyEligibility::CODE_ELIGIBLE,
        'evidence' => [['source' => 'dispatch-fixture-v1']],
    ]),
    new StrategyUsage([
        'cost' => 0.0,
        'latency_ms' => 1,
        'energy' => 0.1,
        'invocations' => 1,
    ])
);

$request = new StrategyRouteRequest([
    'id' => 'route-strategy-example',
    'subject_id' => 'dispatch-agent',
    'capability_id' => 'policy.route',
    'capability_version' => '1.0',
    'candidates' => [['id' => 'tree.dispatch', 'version' => '1.0']],
    'input' => ['preferred' => 'airlift_review'],
    'fixtures' => [['id' => 'dispatch-fixture-v1']],
    'limits' => [
        'max_cost' => 0.0,
        'max_latency_ms' => 10,
        'max_energy' => 0.5,
        'max_invocations' => 1,
    ],
    'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC],
    'deterministic_preferred' => true,
    'correlation_id' => 'correlation-strategy-example',
]);
$trace = new TaskTrace('trace-strategy-example');
$result = (new StrategyRouter([$adapter]))->route($request, $authorization, $trace);
$trace->complete($result->status());

echo json_encode([
    'route' => $result->toArray(),
    'trace' => $trace->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
