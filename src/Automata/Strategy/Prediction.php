<?php
namespace BlueFission\Automata\Strategy;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\DevElation as Dev;

class Prediction extends Strategy
{
    protected $_rules;
    protected $_previous_rule_fired = -1;
    protected $_buffer = [];
    protected $_prediction = null;
    protected $_guesses = 0;
    protected $_success = 0;
    protected $_random;
    protected $_random_success;

    public function __construct()
    {
        $this->_rules = new OrganizedCollection();
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
        $samples = Dev::apply('automata.strategy.prediction.train.1', $samples);
        $labels  = Dev::apply('automata.strategy.prediction.train.2', $labels);
        Dev::do('automata.strategy.prediction.train.action1', ['samples' => $samples, 'labels' => $labels]);

        foreach ($samples as $index => $pattern) {
            $rule = array(
                'matched' => false,
                'pattern' => $pattern,
                'label' => $labels[$index]
            );
            $this->_rules->add($rule, $index);
        }
    }

    /**
     * Predict the next value in the sequence based on the trained patterns.
     *
     * @param mixed $input The current value in the sequence.
     * @return mixed The predicted next value.
     */
    public function predict($input)
    {
        $input = Dev::apply('automata.strategy.prediction.predict.1', $input);
        Dev::do('automata.strategy.prediction.predict.action1', ['input' => $input]);

        $this->_guesses++;
        $rule_to_fire = -1;
        $val = $input;
        $successCounted = false;

        // If the input matches the last prediction, increment success count
        if ($val == $this->_prediction) {
            $this->_success++;
            $successCounted = true;
            if ($this->_previous_rule_fired != -1) {
                $this->_rules->get($this->_previous_rule_fired);
            }
        } else {
            // Sort rules if the last prediction was incorrect
            if ($this->_previous_rule_fired != -1) {
                $this->_rules->sort();
            }

            // Find and update the matching rule
            foreach ($this->_rules as $key => $rule) {
                if ($rule['value']['matched'] && ($rule['value']['pattern'][count($this->_buffer)] == $val)) {
                    $rule['weight']++;
                    $this->_rules->get($key);
                    break;
                }
            }
        }

        // If the input matches the random prediction, increment random success count
        if ($val == $this->_random) {
            $this->_random_success++;
        }

        // Make predictions based on the rules
        $index = 0;
        foreach ($this->_rules as $key => &$rule) {
            $rule['value']['matched'] = true;
            for ($j = 0; $j < count($this->_buffer); $j++) {
                if ($this->_buffer[$j] != $rule['value']['pattern'][$j]) {
                    $rule['value']['matched'] = false;
                    break;
                } else {
                    $rule_to_fire = $key;
                    $index = $j + 1;
                }
            }
        }

        // If a rule is found, set the next prediction
        if ($rule_to_fire != -1) {
            $this->_previous_rule_fired = $rule_to_fire;
            $this->_prediction = $this->_rules->get($rule_to_fire)['pattern'][$index];
        }

        if ($this->_prediction === null) {
            $this->_prediction = $val;
        }

        if (!$successCounted && $this->_prediction == $val) {
            $this->_success++;
        }

        $prediction = $this->_prediction;
        $prediction = Dev::apply('automata.strategy.prediction.predict.2', $prediction);
        Dev::do('automata.strategy.prediction.predict.action2', [
            'input'      => $val,
            'prediction' => $prediction,
            'success'    => $this->_success,
            'guesses'    => $this->_guesses,
        ]);

        return $prediction;
    }

    /**
     * Calculate a simple accuracy metric for the prediction strategy,
     * based on the ratio of successful predictions to total guesses.
     *
     * @return float
     */
    public function accuracy(): float
    {
        if ($this->_guesses === 0) {
            $accuracy = 0.0;
        } else {
            $accuracy = $this->_success / $this->_guesses;
        }

        $accuracy = Dev::apply('automata.strategy.prediction.accuracy.1', $accuracy);
        Dev::do('automata.strategy.prediction.accuracy.action1', ['accuracy' => $accuracy]);

        return $accuracy;
    }
}
