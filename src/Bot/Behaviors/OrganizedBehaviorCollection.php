<?php
namespace BlueFission\Bot\Behaviors;

use BlueFission\Bot\Collections\OrganizedCollection;

class OrganizedBehaviorCollection extends OrganizedCollection {
	public function add( $behavior, $label = null ) {
		if (!$this->has($behavior->name()))
			parent::add( $behavior, $behavior->name() );
	}
}