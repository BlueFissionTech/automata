<?php
namespace BlueFission\Automata\Strategy;

/**
 * Pattern
 *
 * Sequence pattern strategy. Learns simple sequences and, given
 * a buffer of previous values, predicts the next element.
 */
class Pattern extends Basic
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Override train to interpret samples as sequences.
     *
     * Each sample is treated as an ordered list; labels are ignored.
     * Rules are stored as simple flattened sequences.
     *
     * @param array $samples array<int, array<mixed>>
     * @param array $labels
     * @param float $testSize
     */
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $this->_rules = [];
        foreach ($samples as $sequence) {
            if (!is_array($sequence) || count($sequence) < 2) {
                continue;
            }
            $this->_rules[] = array_values($sequence);
        }
    }

    /**
     * Predict the next value in the sequence based on trained patterns.
     *
     * @param mixed $val The current value in the sequence.
     * @return mixed The predicted next value.
     */
    public function predict($val)
    {
        $this->_guesses++;
        $this->_buffer[] = $val;
        $this->_prediction = null;

        foreach ($this->_rules as $rule) {
            $pos = array_search($this->_buffer[0], $rule, true);
            while ($pos !== false) {
                $match = true;
                for ($i = 0; $i < count($this->_buffer); $i++) {
                    if (!isset($rule[$pos + $i]) || $rule[$pos + $i] !== $this->_buffer[$i]) {
                        $match = false;
                        break;
                    }
                }

                if ($match) {
                    $nextIndex = $pos + count($this->_buffer);
                    $this->_prediction = $rule[$nextIndex] ?? null;
                    break 2;
                }

                $pos = array_search($this->_buffer[0], $rule, true);
            }
        }

        if ($this->_prediction === $val) {
            $this->_success++;
        }

        if ($this->_prediction !== null && $this->_prediction !== $val) {
            $this->_buffer = [$val];
        }

        if ($this->_prediction === null && !empty($this->_rules)) {
            $rule = $this->_rules[array_rand($this->_rules)];
            $this->_prediction = $rule[array_rand($rule)];
        }

        return $this->_prediction;
    }
}
