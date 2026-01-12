<?php
namespace BlueFission\Automata\Strategy;

use BlueFission\DevElation as Dev;

class Basic extends Strategy
{
    protected $_success;
    protected $_buffer;
    protected $_prediction;
    protected $_guesses;
    protected $_rules;
    protected $_totaltime;

    public function __construct()
    {
        $this->_buffer = [];
        $this->_rules = [];
        $this->_success = 0;
        $this->_prediction = null;
        $this->_guesses = 0;
        $this->_totaltime = 0;
    }

    /**
     * Train the model with the provided samples and labels.
     *
     * @param array $samples The training samples.
     * @param array $labels The corresponding labels for the training samples.
     * @param float $testSize The proportion of the dataset to include in the test split.
     */
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        // Allow callers to tweak or instrument training data.
        $samples = Dev::apply('automata.strategy.basic.train.1', $samples);
        $labels  = Dev::apply('automata.strategy.basic.train.2', $labels);
        Dev::do('automata.strategy.basic.train.action1', ['samples' => $samples, 'labels' => $labels]);

        foreach ($samples as $index => $pattern) {
            $array = [];
            $array[] = $pattern;
            $array[] = $labels[$index];
            $this->_rules[] = $array;
        }
    }

    /**
     * Predict the next value in the sequence based on the trained rules.
     *
     * @param mixed $val The current value in the sequence.
     * @return mixed The predicted next value.
     */
    public function predict($val)
    {
        // Input can be filtered or observed by DevElation.
        $val = Dev::apply('automata.strategy.basic.predict.1', $val);
        Dev::do('automata.strategy.basic.predict.action1', ['input' => $val]);

        $this->_guesses++;
        $this->_buffer[] = $val;

        $matched = false;
        foreach ($this->_rules as $rule) {
            if ($this->_buffer === $rule[0]) {
                $this->_prediction = $rule[1];
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $this->_prediction = $val;
            $this->_buffer = [];
        } elseif ($this->_prediction != $val) {
            $this->_buffer = [];
        }

        if ($this->_prediction == $val) {
            $this->_success++;
        }

        $prediction = $this->_prediction;

        // Allow post-prediction filters and actions.
        $prediction = Dev::apply('automata.strategy.basic.predict.2', $prediction);
        Dev::do('automata.strategy.basic.predict.action2', [
            'input'      => $val,
            'prediction' => $prediction,
            'success'    => $this->_success,
            'guesses'    => $this->_guesses,
        ]);

        return $prediction;
    }

    /**
     * Calculate the accuracy of the predictions.
     *
     * @return float The accuracy of the model.
     */
    public function accuracy(): float
    {
        if ($this->_guesses === 0) {
            $accuracy = 0.0;
        } else {
            $accuracy = $this->_success / $this->_guesses;
        }

        $accuracy = Dev::apply('automata.strategy.basic.accuracy.1', $accuracy);
        Dev::do('automata.strategy.basic.accuracy.action1', ['accuracy' => $accuracy]);

        return $accuracy;
    }

    /**
     * Save the model to a file.
     *
     * @param string $path The path to save the model.
     * @return bool True if the model was saved successfully, false otherwise.
     */
    public function saveModel(string $path): bool
    {
        $path = Dev::apply('automata.strategy.basic.saveModel.1', $path);
        Dev::do('automata.strategy.basic.saveModel.action1', ['path' => $path, 'model' => $this]);

        try {
            $modelData = serialize($this);
            file_put_contents($path, $modelData);
            Dev::do('automata.strategy.basic.saveModel.action2', ['path' => $path, 'saved' => true]);
            return true;
        } catch (\Exception $e) {
            Dev::do('automata.strategy.basic.saveModel.action3', ['path' => $path, 'saved' => false, 'error' => $e]);
            return false;
        }
    }

    /**
     * Load the model from a file.
     *
     * @param string $path The path to load the model from.
     * @return bool True if the model was loaded successfully, false otherwise.
     */
    public function loadModel(string $path): bool
    {
        $path = Dev::apply('automata.strategy.basic.loadModel.1', $path);
        Dev::do('automata.strategy.basic.loadModel.action1', ['path' => $path]);

        try {
            if (file_exists($path)) {
                $modelData = file_get_contents($path);
                $model = unserialize($modelData);
                foreach (get_object_vars($model) as $property => $value) {
                    $this->$property = $value;
                }
                Dev::do('automata.strategy.basic.loadModel.action2', ['path' => $path, 'loaded' => true, 'model' => $this]);
                return true;
            } else {
                Dev::do('automata.strategy.basic.loadModel.action3', ['path' => $path, 'loaded' => false, 'reason' => 'missing']);
                return false;
            }
        } catch (\Exception $e) {
            Dev::do('automata.strategy.basic.loadModel.action4', ['path' => $path, 'loaded' => false, 'error' => $e]);
            return false;
        }
    }
}
