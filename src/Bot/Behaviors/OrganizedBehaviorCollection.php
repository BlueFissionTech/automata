<?php
namespace BlueFission\Bot\Behaviors;

use BlueFission\Bot\Collections\OrganizedCollection;

class OrganizedBehaviorCollection extends OrganizedCollection {
	public function add( $behavior, $label = null, int $weight = 1 ) {
		if (!$this->has($behavior->name()))
			parent::add( $behavior, $behavior->name(), $weight );
	}
}