<?php

namespace BlueFission\Tests\Examples\DisasterResponse\Sim;

use BlueFission\Automata\GameTheory\PayoffMatrix;
use Examples\DisasterResponse\Sim\Position;
use Examples\DisasterResponse\Sim\ResponderPlayer;
use Examples\DisasterResponse\Sim\ResponderStrategy;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/examples/generic/disaster_response/sim/Position.php';
require_once dirname(__DIR__, 4) . '/examples/generic/disaster_response/sim/ResponderPlayer.php';
require_once dirname(__DIR__, 4) . '/examples/generic/disaster_response/sim/ResponderStrategy.php';

class ResponderStrategyTest extends TestCase
{
    public function testStrategyPrefersUnsatisfiedActions(): void
    {
        $payoffs = new PayoffMatrix();
        $payoffs->setPayoff(['rescue'], [2]);
        $payoffs->setPayoff(['clear'], [2]);
        $payoffs->setPayoff(['move'], [1]);

        $strategy = new ResponderStrategy($payoffs);
        $strategy->setContext([
            'tags' => ['rescue', 'clear'],
            'satisfied' => ['rescue'],
        ]);

        $player = new ResponderPlayer('responder', new Position(0, 0));
        $player->setStrategy($strategy);

        $this->assertSame('clear', $player->decide());
    }

    public function testStrategyFallsBackToMove(): void
    {
        $payoffs = new PayoffMatrix();
        $payoffs->setPayoff(['move'], [1]);

        $strategy = new ResponderStrategy($payoffs);
        $strategy->setContext([
            'tags' => [],
            'satisfied' => [],
        ]);

        $player = new ResponderPlayer('responder', new Position(0, 0));
        $player->setStrategy($strategy);

        $this->assertSame('move', $player->decide());
    }
}
