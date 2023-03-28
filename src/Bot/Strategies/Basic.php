<?php
namespace BlueFission\Bot\Strategies;

class Basic extends Strategy {
	protected $_success;
	protected $_buffer;
	protected $_prediction;
	protected $_guesses;
	protected $_rules;

	public function __construct() {
		$this->_buffer = array();
		$this->_rules = array();
		$this->_success = -1;
		$this->_prediction = 0;
		$this->_totaltime = 0;
	}

	public function train($dataset, float $testSize = 0.2) {
		$pattern = func_get_arg(0);
		$label = func_get_arg(1);

		$array = [];
		$array[] = $pattern;
		$array[] = $label;
		
		$this->_rules[] = $array;
	}

	public function predict( $val ) {
		$this->_guesses++;

		if ($this->_prediction == $val) {
			$this->_success++;
		} else {
			$this->_buffer = array();
		}
		$this->_buffer[] = $val;

		$this->_prediction = $val;

		return $this->_prediction;
	}
	public function accuracy(): float {
		$score = $this->_success / $this->_guesses;
		return $score;
	}
}