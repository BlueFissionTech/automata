<?php
namespace BlueFission\Automata\Strategy;

interface IStrategy
{
    public function train(array $samples, array $labels, float $testSize = 0.2);
    public function predict($input);
    public function accuracy(): float;
    public function saveModel(string $path): bool;
    public function loadModel(string $path): bool;
}
