<?php
namespace BlueFission\Bot\Sensory;

use BlueFission\Behavioral\Dispatcher;
use BlueFission\Collections\Collection;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Data\Queues\MemQueue as Queue;
use BlueFission\Bot\Collections\OrganizedCollection;

class InputArray extends Dispatcher {
	
	const TEXTUAL = 'textual';
	const VISUAL = 'visual';
	const AUDITORY = 'auditory';
	const CINEMATIC = 'cinematic';

	private $_name;
	private $_inputs;
	private $_senses;

	public function __construct($name) {

		parent::__construct();

		$this->_name = $name;
		$this->_inputs = [];
		$this->_senses = [];
	}

	public function create( $label, $processors = [] )
	{
		$input = new Input();

		$input->name($label);
		foreach ($processors as $processor) {
			$input->setProcessor( $processor );
		}
		$input->behavior(Event::COMPLETE, [$this, 'onInputComplete']);

		switch( $label ) {
			default:
			case self::TEXTUAL:
				$sense = new Sense();
			break;
			case self::VISUAL:
				$sense = new Sense();
			break;
			case self::AUDITORY:
				$sense = new Sense();
			break;
			case self::CINEMATIC:
				$sense = new Sense();
			break;
		}

		$sense->behavior(Event::COMPLETE, [$this, 'onParseComplete']);
		$sense->behavior(Event::SUCCESS, [$this, 'onParseSuccess']);

		$this->_inputs[$label] = $input;
		$this->_senses[$label] = $sense;
	}

	public function observe( $package )
	{
		foreach( $package as $key=>$data ) {
			$this->read($data, $key);
		}
	}

	public function read( $data, $type = null )
	{
		$type = $type ?? $this->detect($data);

		if ( isset($this->_inputs[$type]) ) {
			$this->_inputs[$type]->scan($data);
		} else {
			current($this->_inputs)->scan($data);
		}

		// $this->_inputs->get($type)->scan($data);
	}

	public function parse($data, $type = null) 
	{
		$type = $type ?? $this->detect($data);

		$this->_senses[$type]->invoke($data);
	}

	public function process()
	{
		$count = 0;
		$max = 10000;
		while (!Queue::is_empty($this->_name)) {
			$data = Queue::dequeue($this->_name);
			
			if (!is_array($data)) {
				continue;
			}
			$this->reset();

			$this->parse($data[1], $data[0]);
			$count++;
			if ($count >= $max) {
				break;
			}
		}
	}

	public function reset()
	{
		foreach( $this->_senses as $sense ) {
			$sense->reset();
		}
	}

	public function detect( $data )
	{
		return self::TEXTUAL;
	}

	public function onInputComplete( $behavior )
	{
		// Queue::enqueue( $behavior->_target->name(), $behavior->_context );
		Queue::enqueue( $this->_name, [$behavior->_target->name(), $behavior->_context] );
	}

	public function onParseSuccess( $behavior, $data )
	{
		$this->dispatch($behavior, $data[0]);
	}

	public function onParseComplete( $behavior, $data )
	{
		$this->dispatch($behavior, $data[0]);
	}
}