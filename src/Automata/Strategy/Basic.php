<?php
namespace BlueFission\Automata\Strategy;

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
        $this->_prediction = 0;
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
        $this->_guesses++;
        $this->_buffer[] = $val;

        // Check if the buffer matches any rule
        foreach ($this->_rules as $rule) {
            if ($this->_buffer === $rule[0]) {
                $this->_prediction = $rule[1];
                break;
            }
        }

        // If the prediction matches the input, increment success count
        if ($this->_prediction == $val) {
            $this->_success++;
        } else {
            // Clear the buffer if prediction is incorrect
            $this->_buffer = [];
        }

        return $this->_prediction;
    }

    /**
     * Calculate the accuracy of the predictions.
     *
     * @return float The accuracy of the model.
     */
    public function accuracy(): float
    {
        if ($this->_guesses === 0) {
            return 0.0;
        }
        return $this->_success / $this->_guesses;
    }

    /**
     * Save the model to a file.
     *
     * @param string $path The path to save the model.
     * @return bool True if the model was saved successfully, false otherwise.
     */
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

    /**
     * Load the model from a file.
     *
     * @param string $path The path to load the model from.
     * @return bool True if the model was loaded successfully, false otherwise.
     */
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
