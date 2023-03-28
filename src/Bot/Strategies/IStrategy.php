<?php
namespace BlueFission\Bot\Strategies;

interface IStrategy
{
    public function train($dataset, float $testSize = 0.2);
    public function predict($input);
    public function accuracy(): float;
}