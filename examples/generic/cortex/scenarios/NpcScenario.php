<?php

namespace CortexExample\Scenarios;

use BlueFission\Arr;
use Fiber;
use LogicException;

/** Interactive scripted NPC: interrupt a trade, then finish a changed quest. */
final class NpcScenario
{
    /** Host world state is authoritative; proposed dialogue cannot award items. */
    public function run(): array
    {
        $world = ['location' => 'market', 'coins' => 3, 'potion' => 0, 'quest_complete' => false];
        $effects = [];
        $host = new EffectHost('npc', static function ($proposal) use (&$world, &$effects): string {
            if ($proposal === ['action' => 'escort', 'destination' => 'shelter']) {
                $world['location'] = 'shelter';
                $world['quest_complete'] = true;
            } else {
                throw new LogicException('The current quest does not authorize a trade.');
            }
            $effects[] = $proposal;
            return 'quest complete';
        });
        $trade = ScenarioRuntime::workflow('npc-trade', [
            'offer' => static fn () => Fiber::suspend('await_player_trade_choice'),
            'purchase' => static fn () => ['action' => 'buy', 'item' => 'potion'],
        ], [['offer', 'purchase']], ['purchase'], ['coins' => 3]);
        $waiting = new Fiber(fn () => $trade->execute('offer'));
        $waiting->start();
        $waits = $trade->ready() === [] && $effects === [];

        // Player interrupts with "escort me to shelter". The late trade answer
        // may settle its worker receipt but cannot unlock the old purchase.
        $trade->cancel();
        $waiting->resume('buy potion');
        $quest = ScenarioRuntime::workflow('npc-escort', [
            'route' => static fn () => ['destination' => 'shelter', 'safe' => true],
            'escort' => static fn ($input) => ['action' => 'escort',
                'destination' => $input['predecessors']['route']['output']['destination']],
        ], [['route', 'escort']], ['escort'], ['goal' => 'protect-player']);
        $plan = ScenarioRuntime::finish($quest);
        $response = $host->response('escort-response', $plan['outputs']['escort']);
        $release = $response->prepare(0);
        $before = $world;
        $pendingWithoutEffect = $response->prepare(1) === $release && !$world['quest_complete'];
        $response->acknowledge($release['id'], $host->deliver($release, true));
        $confirmation = $response->prepare(2);
        $expected = ['location' => 'shelter', 'coins' => 3, 'potion' => 0, 'quest_complete' => true];
        $claim = ['location' => 'shelter', 'quest_complete' => true, 'potion' => 0];
        $checks = [
            'waits_for_player_choice_without_effect' => $waits,
            'interrupted_trade_cannot_purchase' => $trade->result()->outputs() === [] && $trade->result()->status() === 'cancelled',
            'pending_proposal_is_not_quest_completion' => $pendingWithoutEffect,
            'world_matches_changed_goal' => $world === $expected && Arr::size($effects) === 1,
            'narrative_and_inventory_agree' => self::consistent($claim, $world)
                && $confirmation['fragments'][0]['payload']['proposal'] === ['action' => 'escort', 'destination' => 'shelter'],
            'invented_item_control_rejected' => !self::consistent([...$claim, 'potion' => 1], $world),
            'premature_completion_control_rejected' => !self::consistent($claim, $before),
        ];
        return ['scenario' => 'npc-v1', 'status' => Arr::has($checks, false, true) ? 'FAIL' : 'PASS',
            'checks' => $checks, 'world' => $world, 'effects' => $effects, 'workflow' => $plan,
            'scope' => 'Scripted player interaction with a synthetic world; no claim of open-world NPC intelligence.'];
    }

    /** Compare every asserted fact with the host world, independently of text. */
    public static function consistent(array $claim, array $world): bool
    {
        foreach ($claim as $key => $value) {
            if (!Arr::hasKey($world, $key) || $world[$key] !== $value) { return false; }
        }
        return $claim !== [];
    }
}
