<?php

namespace BlueFission\Bot\Behaviors;

use BlueFission\Bot\Collections\OrganizedCollection;
use BlueFission\Exceptions\NotImplementedException;
use BlueFission\Behavioral\Behaviors\Behavior;

class OrganizedHandlerCollection extends OrganizedCollection {
	public function add($handler, $priority = null)
	{
		$handler->priority($priority);
		// $this->_value->append($handler);
		parent::add( $handler, uniqid('handler_', true) );
		$this->prioritize();
	}

	public function get( $behaviorName )
	{
		// throw new NotImplementedException('Function Not Implemented');
		$handlers = array();

		foreach ($this->_value as $name=>$handler)
		{
			if ($handler['value']->name() == $behaviorName) {
				// $handlers[] = $c;
				$handlers[] = $this->get($name);
			}
		}
		return $handlers;
	}

	public function raise($behavior, $sender, $args)
	{
		if (is_string($behavior))
			$behavior = new Behavior($behavior);

		$behavior->_target = $behavior->_target ? $behavior->_target : $sender;

		foreach ($this->_value as $c)
		{
			if ($c['value']->name() == $behavior->name())
			{
				$c['value']->raise($behavior, $args);
			}
		}
	}

	private function prioritize()
	{
		$this->sort();
	}

	protected function create($value) {
		return array('weight'=>$value->priority(), 'value'=>$value, 'decay'=>$this->_decay, 'timestamp'=>time());
	}

}