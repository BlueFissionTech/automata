<?php

namespace BlueFission\Automata\Goal;

use BlueFission\Arr;

class GoalDecision extends InitiativeObject
{
    /**
     * Build a deterministic goal decision option.
     */
    public static function option(string $action, float $score = 0.0, array $metadata = []): self
    {
        return new self([
            'action' => $action,
            'score' => $score,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Return the proposed action or tool/action hint.
     */
    public function action(): string
    {
        return (string)$this->field('action');
    }

    /**
     * Return the deterministic score for this option.
     */
    public function score(): float
    {
        return (float)$this->field('score');
    }

    /**
     * Export the option in a prompt- and trace-friendly shape.
     */
    public function toArray(): array
    {
        return Arr::make([
            'action' => $this->action(),
            'score' => $this->score(),
            'goal' => $this->field('goal'),
            'criterion' => $this->field('criterion'),
            'reason' => $this->field('reason'),
            'metadata' => Arr::make($this->field('metadata'))->toArray(),
        ])->toArray();
    }
}
