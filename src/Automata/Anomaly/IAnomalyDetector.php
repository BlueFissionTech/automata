<?php

namespace BlueFission\Automata\Anomaly;

use BlueFission\Automata\Context;
use BlueFission\Automata\Strategy\IStrategy;

interface IAnomalyDetector extends IStrategy
{
    public function score($input, Context $context, array $options = []): float;

    public function detect($input, Context $context, array $options = []): bool;

    public function setThreshold(float $threshold): void;

    public function threshold(): float;
}
