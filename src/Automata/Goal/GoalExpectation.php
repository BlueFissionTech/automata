<?php

namespace BlueFission\Automata\Goal;

class GoalExpectation extends InitiativeObject
{
    /**
     * Create an expectation for a behavior to satisfy a criterion.
     */
    public static function forBehavior(string $behavior, string $criterionKey, float $ttlSeconds = 60.0, string $reason = ''): self
    {
        return new self([
            'behavior' => $behavior,
            'criterion_key' => $criterionKey,
            'expires_at' => microtime(true) + $ttlSeconds,
            'reason' => $reason,
            'fulfilled' => false,
        ]);
    }

    /**
     * Return the behavior being tracked.
     */
    public function behavior(): string
    {
        return (string)$this->field('behavior');
    }

    /**
     * Return the criterion key the behavior is expected to satisfy.
     */
    public function criterionKey(): string
    {
        return (string)$this->field('criterion_key');
    }

    /**
     * Mark the expectation as fulfilled.
     */
    public function fulfill(): self
    {
        $this->field('fulfilled', true);

        return $this;
    }

    /**
     * Determine whether the expectation has already been fulfilled.
     */
    public function fulfilled(): bool
    {
        return (bool)$this->field('fulfilled');
    }

    /**
     * Determine whether the expectation has expired.
     */
    public function expired(?float $now = null): bool
    {
        $now = $now ?? microtime(true);

        return !$this->fulfilled() && ((float)$this->field('expires_at') < $now);
    }

    /**
     * Export the expectation for traces and state channels.
     */
    public function toArray(): array
    {
        return [
            'behavior' => $this->behavior(),
            'criterion_key' => $this->criterionKey(),
            'expires_at' => (float)$this->field('expires_at'),
            'reason' => $this->field('reason'),
            'fulfilled' => $this->fulfilled(),
        ];
    }
}
