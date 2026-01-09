<?php

namespace BlueFission\Tests\Automata\GameTheory;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\GameTheory\Game;
use BlueFission\Automata\GameTheory\Player;

class CountingStrategy
{
    public int $calls = 0;

    public function decide(Player $player): void
    {
        $this->calls++;
    }
}

class GameTheoryTest extends TestCase
{
    public function testGameInvokesPlayerStrategiesPerRound(): void
    {
        $rounds = 3;
        $game   = new Game($rounds);

        $playerA = new Player('A');
        $playerB = new Player('B');

        $strategyA = new CountingStrategy();
        $strategyB = new CountingStrategy();

        $playerA->setStrategy($strategyA);
        $playerB->setStrategy($strategyB);

        $game->addPlayer($playerA);
        $game->addPlayer($playerB);

        $game->play();

        $this->assertSame($rounds, $strategyA->calls);
        $this->assertSame($rounds, $strategyB->calls);
    }
}

