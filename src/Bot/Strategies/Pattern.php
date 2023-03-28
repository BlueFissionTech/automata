<?php
namespace BlueFission\Bot\Strategies;

class Pattern extends Basic {

	public function __construct() {
		parent::__construct();
	}

	public function predict( $val ) {
		parent::predict($val);

		foreach ($this->_rules as $rule) {
			$position = true;

			while( $position != false ) {
				$position = array_search( $val, $rule );
				for ($i = 0; $i<count($rule); $i++ ) {
					if ( $this->_buffer[$i] != $rule[$position+$i]) {
						$this->_prediction = null;
						break;
					}
					$this->_prediction = $rule[$position+$i+1];
					$position = false;
					break;
				}
			}

			return $this->_prediction;
		}

		if ( $this->_prediction == null ) {
			$rule = $this->_rules[rand(0, count($this->_rules)-1)];
			$this->_prediction = $rule[rand(0, count($rule)-1)];
		}
	}
}