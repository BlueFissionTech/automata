<?php

/*
 resolve Gestalt
 Proximity
 Similarity
 Continuity
 Pragnanz (simplicity)
 Symmetry
 Closure
 Common Fate

*/
namespace BlueFission\Intelligence;

use BlueFission\Behavioral\Dispatcher;

class Holoscene extends Dispatcher {
	protected $_holo;

	private $_assessment;

	public function push( $input, $frame ) {
		if ( $_holo->has($input) ) {
			$scene = $_holo->get($input);
			$scene->add($frame, (string)$scene);
		}
	}

	public function review() {
		$map = new OrganizedCollection();
		foreach ( $_holo->toArray() as $key=>$scene ) {
			$scene->stats();
			$data = $scene->data();
			$map->add($data, $scene);
		}
		$map->stats();
		$this->_assessment = $map->data();
	}

	public function assessment() {
		return $this->_assessment;
	}
}