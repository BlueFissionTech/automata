<?php

namespace BlueFission\Automata\Behaviors;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Exceptions\NotImplementedException;
use BlueFission\Behavioral\Behaviors\Behavior;

class OrganizedHandlerCollection extends OrganizedCollection {
	public function add($handler, $label = null, int $priority = null )
	{
		$weight = $priority ?? 1;
		$handler->priority($priority);
		// $this->_value->append($handler);
		parent::add( $handler, $label ?? uniqid('handler_', true), $weight );
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
				$handlers[] = $handler['value'];
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

	protected function create($value, int $priority = 1) {
		return array('weight'=>$priority ?? $value->priority(), 'value'=>$value, 'decay'=>$this->_decay, 'timestamp'=>time());
	}
}