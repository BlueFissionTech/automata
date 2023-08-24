<?php
namespace BlueFission\Automata\GameTheory;

use BlueFission\Behavioral\StateMachine;

class Game {
    private $players = [];
    private $rounds = 0;

    public function __construct($rounds = 1) {
        $this->rounds = $rounds;
    }

    public function addPlayer(Player $player) {
        $this->players[] = $player;
    }

    public function play() {
        for ($i = 0; $i < $this->rounds; $i++) {
            foreach ($this->players as $player) {
                $player->decide();
            }
        }
    }
}
