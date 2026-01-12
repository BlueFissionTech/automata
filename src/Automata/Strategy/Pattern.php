<?php
namespace BlueFission\Automata\Strategy;

use BlueFission\DevElation as Dev;

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
        $samples = Dev::apply('automata.strategy.pattern.train.1', $samples);
        $labels  = Dev::apply('automata.strategy.pattern.train.2', $labels);
        Dev::do('automata.strategy.pattern.train.action1', ['samples' => $samples, 'labels' => $labels]);

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
        $val = Dev::apply('automata.strategy.pattern.predict.1', $val);
        Dev::do('automata.strategy.pattern.predict.action1', ['input' => $val]);

        $this->_guesses++;
        $this->_buffer[] = $val;
        $this->_prediction = null;

        $bufferLength = count($this->_buffer);

        foreach ($this->_rules as $rule) {
            $ruleLength = count($rule);

            // Scan the rule for any position where the buffer prefix matches.
            // This avoids re-running array_search at the same index and
            // guarantees that we always advance through the rule even when
            // only the first element of the buffer matches.
            for ($pos = 0; $pos <= $ruleLength - $bufferLength; $pos++) {
                if ($rule[$pos] !== $this->_buffer[0]) {
                    continue;
                }

                $match = true;
                for ($i = 1; $i < $bufferLength; $i++) {
                    if ($rule[$pos + $i] !== $this->_buffer[$i]) {
                        $match = false;
                        break;
                    }
                }

                if ($match) {
                    $nextIndex = $pos + $bufferLength;
                    $this->_prediction = $rule[$nextIndex] ?? null;
                    break 2;
                }
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

        $prediction = $this->_prediction;
        $prediction = Dev::apply('automata.strategy.pattern.predict.2', $prediction);
        Dev::do('automata.strategy.pattern.predict.action2', ['input' => $val, 'prediction' => $prediction]);

        return $prediction;
    }
}
