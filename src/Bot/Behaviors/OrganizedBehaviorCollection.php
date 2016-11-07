<?php
namespace BlueFission\Intelligence\Behaviors;

use BlueFission\Intelligence\Collections\OrganizedCollection;

class OrganizedBehaviorCollection extends OrganizedCollection {
	public function add( &$behavior, $label = null ) {
		if (!$this->has($behavior->name()))
			parent::add( $behavior, $behavior->name() );
	}
}