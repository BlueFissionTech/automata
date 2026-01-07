<?php
namespace BlueFission\Automata\GameTheory;

use BlueFission\Behavioral\StateMachine;
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
        $this->_players->push($player);
    }

    public function play() {
        for ($i = 0; $i < $this->_rounds; $i++) {
            $this->_players->each(function($player) {
                $player->decide();
            });
        }
    }
}
