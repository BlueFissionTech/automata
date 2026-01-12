<?php
namespace BlueFission\Automata\Strategy;

use BlueFission\DevElation as Dev;

abstract class Strategy implements IStrategy
{
    protected $_testSamples;
    protected $_testTargets;

    abstract public function train(array $samples, array $labels, float $testSize = 0.2);
    abstract public function predict($input);
    abstract public function accuracy(): float;

    /**
     * Persist a trained model to storage.
     *
     * The path is passed through DevElation filters and an action
     * is fired so callers can instrument or reroute persistence.
     */
    public function saveModel(string $path): bool
    {
        $path = Dev::apply('automata.strategy.strategy.saveModel.1', $path);
        Dev::do('automata.strategy.strategy.saveModel.action1', ['path' => $path, 'model' => $this]);

        try {
            $modelData = serialize($this);
            file_put_contents($path, $modelData);
            Dev::do('automata.strategy.strategy.saveModel.action2', ['path' => $path, 'saved' => true]);
            return true;
        } catch (\Exception $e) {
            Dev::do('automata.strategy.strategy.saveModel.action3', ['path' => $path, 'saved' => false, 'error' => $e]);
            return false;
        }
    }

    /**
     * Restore a previously persisted model from storage.
     *
     * The path is filterable and load attempts are observable
     * via DevElation actions.
     */
    public function loadModel(string $path): bool
    {
        $path = Dev::apply('automata.strategy.strategy.loadModel.1', $path);
        Dev::do('automata.strategy.strategy.loadModel.action1', ['path' => $path]);

        try {
            if (file_exists($path)) {
                $modelData = file_get_contents($path);
                $model = unserialize($modelData);
                foreach (get_object_vars($model) as $property => $value) {
                    $this->$property = $value;
                }
                Dev::do('automata.strategy.strategy.loadModel.action2', ['path' => $path, 'loaded' => true, 'model' => $this]);
                return true;
            } else {
                Dev::do('automata.strategy.strategy.loadModel.action3', ['path' => $path, 'loaded' => false, 'reason' => 'missing']);
                return false;
            }
        } catch (\Exception $e) {
            Dev::do('automata.strategy.strategy.loadModel.action4', ['path' => $path, 'loaded' => false, 'error' => $e]);
            return false;
        }
    }
}
