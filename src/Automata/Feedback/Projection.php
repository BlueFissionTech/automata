<?php

namespace BlueFission\Automata\Feedback;

use BlueFission\DevElation as Dev;

class Projection extends FeedbackItem
{
    public function __construct(array $data = [])
    {
        $data['ttl'] = $data['ttl'] ?? 60.0;
        parent::__construct($data);

        $expiresAt = $this->timestamp() + (float)$this->field('ttl');
        $this->field('expires_at', $expiresAt);

        Dev::do('feedback.projection.created', ['projection' => $this]);
    }

    public function expiresAt(): float
    {
        $expiresAt = $this->field('expires_at');
        return is_numeric($expiresAt) ? (float)$expiresAt : 0.0;
    }

    public function isExpired(float $now = null): bool
    {
        $now = $now ?? microtime(true);
        return $this->expiresAt() < $now;
    }
}
