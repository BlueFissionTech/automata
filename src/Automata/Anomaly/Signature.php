<?php

namespace BlueFission\Automata\Anomaly;

use BlueFission\DevElation as Dev;

class Signature extends Fingerprint
{
    protected float $_threshold = 0.7;

    public function __construct(array $data = [], float $threshold = 0.7)
    {
        parent::__construct($data);
        $this->_threshold = $threshold;
        Dev::do('anomaly.signature.created', ['signature' => $this]);
    }

    public function threshold(): float
    {
        return $this->_threshold;
    }

    public function match(Fingerprint $fingerprint): float
    {
        return $this->similarity($fingerprint);
    }

    public function matches(Fingerprint $fingerprint): bool
    {
        return $this->match($fingerprint) >= $this->_threshold;
    }
}
