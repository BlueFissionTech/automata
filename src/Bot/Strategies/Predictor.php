<?php
namespace BlueFission\Bot\Strategies;

use BlueFission\Bot\Collections\OrganizedCollection;

class Predictor extends Strategy {

	private $_rules;
	private $_previous_rule_fired = -1;

	private $_random;

	private $_random_success;

	public function __construct() {
		parent::__construct();
		$this->_rules = new OrganizedCollection();
	}

	public function train( ) {
		$pattern = func_get_args();
		$rule = array(
			'matched'=>false,
			'pattern'=>$pattern
		);

		$this->_rules->add($rule);
	}

	public function process( $val ) {
		$i = 0;
		$rule_to_fire = -1;

		if ( $val == $this->_prediction ) {
			$this->_success++;
			if ($this->_previous_rule_fired != -1) {
				$this->_rules->get($this->_previous_rule_fired);
			}
		} else {
			if ($this->_previous_rule_fired != -1) {
				$this->_rules->sort();
			}

			foreach ( $this->_rules as $key=>&$rule ) {
				if ($rule['value']['matched'] && ($rule['value']['pattern'][count($this->_buffer)]) == $val) {
					$rule['weight']++;
					// $this->_rules->add($rule, $key);
					$this->_rules->get($key);
					break;
				}
			}
			unset($rule);
		}

		if ( $val == $this->_random ) {
			$this->_random_success++;
		}

		// Make predictions
		$index = 0;
		foreach ( $this->_rules as $key=>&$rule ) {
			$rule['value']['matched'] == true;
			for ( $j=0;$j<count($this->_buffer);$j++ ) {
				if ( $this->_buffer[$j] != $rule['value']['pattern'][$j] ) {
					$rule['value']['matched'] == false;
					break;
				} else {
					$rule_to_fire = $key;
					$index = $j+1;
				}
			}
		}
		unset($this->_rules[$key]);

		if ($rule_to_fire != -1) {
			$this->_previous_rule_fired = $rule_to_fire;
		}

		$this->_prediction = $this->_rules[$rule_to_fire]['pattern'][$index];
	}
}