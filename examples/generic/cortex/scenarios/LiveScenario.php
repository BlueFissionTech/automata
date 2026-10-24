<?php

namespace CortexExample\Scenarios;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Strategy\Routing\{StrategyRouteRequest, StrategyRouter};
use BlueFission\Automata\Support\RecordSnapshot;

/**
 * Small live proposal qualification across the same four domains.
 *
 * Provider advice passes through StrategyRouter. The oracle is outside the
 * request and a response is never an execution receipt. This is eight bounded
 * decisions per provider, not a claim of live long-horizon game mastery.
 */
final class LiveScenario
{
    /** Frozen public inputs and independently specified expected decisions. */
    public static function cases(): array
    {
        return [
            ['id' => 'conversation-missing', 'input' => ['domain' => 'booking', 'reference' => null],
                'expected' => ['action' => 'ask_reference', 'completed' => false]],
            ['id' => 'conversation-changed', 'input' => ['domain' => 'booking', 'reference' => 'BOOK-42', 'previous_reference' => 'OLD-REF'],
                'expected' => ['action' => 'plan_checkin', 'completed' => false, 'reference' => 'BOOK-42']],
            ['id' => 'operations-lost-ack', 'input' => ['domain' => 'operations', 'receiver_receipt' => 'succeeded', 'caller_ack' => false, 'approved' => true],
                'expected' => ['action' => 'reconcile', 'completed' => false]],
            ['id' => 'operations-revoked', 'input' => ['domain' => 'operations', 'receiver_receipt' => null, 'approved' => false],
                'expected' => ['action' => 'wait', 'completed' => false]],
            ['id' => 'game-five', 'input' => ['domain' => 'game', 'remaining' => 5],
                'expected' => ['action' => 'move', 'completed' => false, 'take' => 1]],
            ['id' => 'game-six', 'input' => ['domain' => 'game', 'remaining' => 6],
                'expected' => ['action' => 'move', 'completed' => false, 'take' => 2]],
            ['id' => 'npc-interrupt', 'input' => ['domain' => 'npc', 'previous_goal' => 'buy potion',
                'latest_goal' => 'escort', 'destination' => 'shelter', 'route_safe' => true],
                'expected' => ['action' => 'escort', 'completed' => false, 'destination' => 'shelter']],
            ['id' => 'npc-unsafe', 'input' => ['domain' => 'npc', 'latest_goal' => 'escort',
                'destination' => 'shelter', 'route_safe' => false],
                'expected' => ['action' => 'wait', 'completed' => false]],
        ];
    }

    /** Object equality ignores key order, but rejects extra authority or facts. */
    public static function accepted(mixed $proposal, array $expected): bool
    {
        return Arr::is($proposal)
            && RecordSnapshot::canonical($proposal) === RecordSnapshot::canonical($expected);
    }

    /** Fail a provider on transport failure; do not silently retry or substitute. */
    public function run(string $provider): array
    {
        $transport = new LiveProvider($provider);
        $adapter = new ScenarioAdapter('live.' . $provider, $transport->propose(...), mode: 'generative');
        $router = new StrategyRouter([$adapter]);
        $rows = [];
        foreach (self::cases() as $case) {
            $route = $router->route(new StrategyRouteRequest([
                'id' => $case['id'], 'subject_id' => 'live-proof-host',
                'capability_id' => 'scenario.propose', 'capability_version' => '1',
                'candidates' => [['id' => 'live.' . $provider, 'version' => '1']],
                'allowed_modes' => ['generative'], 'input' => $case['input'],
                'limits' => ['max_invocations' => 1],
            ]), new AutonomyDecision(['allowed' => true, 'subject_id' => 'live-proof-host',
                'code' => AutonomyDecision::CODE_ALLOWED,
                'capability_id' => 'scenario.propose', 'capability_version' => '1']));
            $rows[] = ['id' => $case['id'], 'status' => $route->completed() ? 'observed' : 'unavailable',
                'route_code' => $route->code, 'proposal' => $route->output,
                'accepted' => $route->completed() && self::accepted($route->output, $case['expected'])];
            if (!$route->completed()) { break; }
        }
        $passed = Arr::size($rows) === Arr::size(self::cases())
            && Arr::make($rows)->filter(static fn ($row) => !$row['accepted'])->size() === 0;
        $unavailable = Arr::make($rows)->filter(static fn ($row) => $row['status'] === 'unavailable')->size() > 0;
        $correction = null;
        if (!$unavailable && (!$rows[4]['accepted'] || !$rows[5]['accepted'])) {
            $correction = (new RoutingCorrection())->run([5 => $rows[4]['proposal'], 6 => $rows[5]['proposal']]);
        }
        return ['provider' => $provider, 'status' => $passed ? 'PASS' : ($unavailable ? 'BLOCKED' : 'FAIL'),
            'rows' => $rows, 'receipts' => $transport->receipts, 'effects' => 0,
            'routing_correction' => $correction,
            'scope' => 'Live proposal-only fixture qualification; no tool authority, production actions or billing estimate.'];
    }
}
