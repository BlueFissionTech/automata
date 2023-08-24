<?php
namespace BlueFission\Automata\GameTheory;

use BlueFission\Behavioral\StateMachine;

class Player {
    use StateMachine;

    private $name;
    private $strategy;

    public function __construct($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setStrategy($strategy) {
        $this->strategy = $strategy;
    }

    public function getStrategy() {
        return $this->strategy;
    }

    public function decide() {
        return $this->strategy->decide($this);
    }

    public function addAction($stateName, $actionName, callable $action, array $allowedStates, array $deniedStates) {
        $this->behavior($stateName, function() use ($actionName, $allowedStates, $deniedStates) {
            $this->allows($actionName, $allowedStates);
            $this->denies($actionName, $deniedStates);
        });

        $this->action($actionName, $action);
    }

    public function addState($stateName) {
        $this->behavior($stateName, function() {});
    }

    public function changeState($stateName) {
        if ($this->can($stateName)) {
            $this->dispatch($stateName);
        }
    }
}