<?php

namespace Examples\DisasterResponse\Sim;

use BlueFission\Automata\GameTheory\PayoffMatrix;

class ResponderStrategy
{
    private PayoffMatrix $payoffs;
    private array $context = [];

    public function __construct(PayoffMatrix $payoffs)
    {
        $this->payoffs = $payoffs;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function decide($player): string
    {
        $actions = ['rescue', 'clear', 'repair', 'deliver', 'move'];
        $tags = $this->context['tags'] ?? [];
        $satisfied = $this->context['satisfied'] ?? [];

        $bestAction = 'move';
        $bestScore = $this->basePayoff('move');

        foreach ($actions as $action) {
            $score = $this->basePayoff($action);

            if ($action !== 'move' && !in_array($action, $tags, true)) {
                $score -= 3.0;
            }

            if (in_array($action, $tags, true)) {
                $score += 1.5;
            }

            if (in_array($action, $satisfied, true)) {
                $score -= 2.0;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestAction = $action;
            }
        }

        return $bestAction;
    }

    private function basePayoff(string $action): float
    {
        $payoff = $this->payoffs->getPayoff([$action]);
        $value = is_array($payoff) ? ($payoff[0] ?? 0.0) : 0.0;
        return is_numeric($value) ? (float)$value : 0.0;
    }
}
