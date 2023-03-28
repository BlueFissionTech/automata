<?php
namespace BlueFission\Bot\Strategies;

abstract class Strategy implements IStrategy
{
    protected $testSamples;
    protected $testTargets;

    abstract public function train($dataset, float $testSize = 0.2);
    abstract public function predict($input);
    abstract public function accuracy(): float;
}