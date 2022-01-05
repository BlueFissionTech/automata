<?php

namespace BlueFission\Bot\Sensory;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Behavior;
use BlueFission\Behavioral\Dispatcher;
use BlueFission\Collections\Collection;

class Input extends Dispatcher {

	protected $_processors;

	public function __construct( $processor = null ) {
		parent::__construct();

		if ( !$processor ) {
			$processor = function( $data ) {
				return $data;
			};
		}

		$this->_processors = new Collection();
		$this->_processors[] = $processor;
	}

	public function setProcessor( $processorFunction ) {
		$this->_processors[] = $processorFunction;
	}

	public function scan( $data, $processor = null ) {
		if ( $processor ) {
			$this->_processors[] = $processor;
		}

		foreach( $this->_processors as $processor ) {
			// $data = call_user_func_array($processor['value'], array($data));
			// $data = call_user_func_array($processor, array($data));
		}

		$this->dispatch( Event::COMPLETE, $data );
	}

	public function dispatch( $behavior, $args = null) {

		if (is_string($behavior)) {
			$behavior = new Behavior($behavior);
			$behavior->_target = $this;
		}

		if ($behavior->_target == $this) {
			$behavior->_context = $args;
			$args = null;
		}

		parent::dispatch($behavior, $args);
	}

	protected function init() {
		parent::init();
	}
}