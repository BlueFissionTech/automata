<?php
namespace BlueFission\Automata\Behaviors;

use BlueFission\Automata\Collections\OrganizedCollection;

class OrganizedBehaviorCollection extends OrganizedCollection {
	public function add( $behavior, $label = null, int $weight = 1 ) {
		if (!$this->has($behavior->name()))
			parent::add( $behavior, $behavior->name(), $weight );
	}
}