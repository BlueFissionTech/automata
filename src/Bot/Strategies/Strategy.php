<?php
namespace BlueFission\Bot\Strategies;

abstract class Strategy implements IStrategy
{
    protected $testSamples;
    protected $testTargets;

    abstract public function train(array $samples, array $labels, float $testSize = 0.2);
    abstract public function predict($input);
    abstract public function accuracy(): float;
}