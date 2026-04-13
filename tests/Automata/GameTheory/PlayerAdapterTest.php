<?php

namespace BlueFission\Tests\Automata\GameTheory;

use BlueFission\Automata\GameTheory\Player;
use BlueFission\Automata\Goal\Initiative;
use PHPUnit\Framework\TestCase;

class PlayerAdapterTest extends TestCase
{
    public function testPlayerTracksInterpreterFriendlyDecisionState(): void
    {
        $player = new Player('Continuity Lead');
        $strategy = new class {
            public function decide(Player $player): array
            {
                return ['action' => 'reroute', 'confidence' => 0.82];
            }
        };

        $player
            ->role('operator')
            ->scope('circumstantial')
            ->awareness('local')
            ->efficacy('can_make_now')
            ->addGoal(new Initiative(['name' => 'Maintain service']))
            ->addCriterion('continuity', 2, 'stability')
            ->addStrategy('decision_tree', 1, 'routing')
            ->record('input_observed', ['source' => 'sensor'])
            ->setStrategy($strategy);

        $decision = $player->decide();

        $snapshot = $player->snapshot();

        $this->assertSame('Continuity Lead', $snapshot['name']);
        $this->assertSame('operator', $snapshot['role']);
        $this->assertSame(1, $snapshot['goalCount']);
        $this->assertSame(1, $snapshot['criterionCount']);
        $this->assertSame(2, $snapshot['strategyCount']);
        $this->assertSame(2, $snapshot['historyCount']);
        $this->assertSame(['action' => 'reroute', 'confidence' => 0.82], $decision);
        $this->assertSame(['action' => 'reroute', 'confidence' => 0.82], $snapshot['lastDecision']);
        $this->assertSame(get_class($strategy), $snapshot['activeStrategy']);
        $this->assertStringContainsString('Continuity Lead', $player->explain());
    }
}
