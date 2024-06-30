<?php
namespace BlueFission\Automata\Strategy;

abstract class Strategy implements IStrategy
{
    protected $_testSamples;
    protected $_testTargets;

    abstract public function train(array $samples, array $labels, float $testSize = 0.2);
    abstract public function predict($input);
    abstract public function accuracy(): float;

    public function saveModel(string $path): bool
    {
        try {
            $modelData = serialize($this);
            file_put_contents($path, $modelData);
            return true;
        } catch (\Exception $e) {
            // Handle the exception
            return false;
        }
    }

    public function loadModel(string $path): bool
    {
        try {
            if (file_exists($path)) {
                $modelData = file_get_contents($path);
                $model = unserialize($modelData);
                foreach (get_object_vars($model) as $property => $value) {
                    $this->$property = $value;
                }
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            // Handle the exception
            return false;
        }
    }
}
