<?php
namespace BlueFission\Automata\Strategy;

class Pattern extends Basic
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Predict the next value in the sequence based on the trained patterns.
     *
     * @param mixed $val The current value in the sequence.
     * @return mixed The predicted next value.
     */
    public function predict($val)
    {
        parent::predict($val);

        // Iterate through the rules to find a matching pattern
        foreach ($this->_rules as $rule) {
            $position = true;

            while ($position !== false) {
                // Find the position of the current value in the rule
                $position = array_search($val, $rule);
                if ($position !== false) {
                    for ($i = 0; $i < count($rule); $i++) {
                        // Check if the current buffer matches the rule pattern
                        if (isset($this->_buffer[$i]) && $this->_buffer[$i] != $rule[$position + $i]) {
                            $this->_prediction = null;
                            break;
                        }
                        // Predict the next value in the pattern
                        $this->_prediction = $rule[$position + $i + 1] ?? null;
                        $position = false;
                        break;
                    }
                }
            }

            // If a prediction is found, return it
            if ($this->_prediction !== null) {
                return $this->_prediction;
            }
        }

        // If no pattern is found, randomly predict a value from a random rule
        if ($this->_prediction === null) {
            $rule = $this->_rules[rand(0, count($this->_rules) - 1)];
            $this->_prediction = $rule[rand(0, count($rule) - 1)];
        }

        return $this->_prediction;
    }
}
