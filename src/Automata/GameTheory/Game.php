<?php
namespace BlueFission\Automata\GameTheory;

use BlueFission\Behavioral\StateMachine;
use BlueFission\DevElation as Dev;
use BlueFission\Arr;

class Game {
    use StateMachine;

    private $_players;
    private $_rounds = 0;

    public function __construct($rounds = 1) {
        $this->_rounds = $rounds;
        $this->_players = new Arr([]);
    }

    public function addPlayer(Player $player) {
        $player = Dev::apply('automata.gametheory.game.addPlayer.1', $player);
        $this->_players->push($player);
        Dev::do('automata.gametheory.game.addPlayer.action1', ['player' => $player]);
    }

    public function play() {
        Dev::do('automata.gametheory.game.play.action1', ['rounds' => $this->_rounds]);
        for ($i = 0; $i < $this->_rounds; $i++) {
            $this->_players->each(function($player) use ($i) {
                $decision = $player->decide();
                Dev::do('automata.gametheory.game.play.action2', [
                    'round'    => $i,
                    'player'   => $player,
                    'decision' => $decision,
                ]);
            });
        }
    }
}
