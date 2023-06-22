<?php
namespace BlueFission\Bot\Strategies;

interface IStrategy
{
    public function train(array $samples, array $labels, float $testSize = 0.2);
    public function predict($input);
    public function accuracy(): float;
}