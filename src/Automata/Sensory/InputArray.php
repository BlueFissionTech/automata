<?php
namespace BlueFission\Automata\Sensory;

use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Collections\Collection;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Data\Queues\MemQueue as Queue;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\InputType;
use BlueFission\DevElation as Dev;

class InputArray implements IDispatcher {
	use Dispatches {
		Dispatches::__construct as private __dispatchesConstruct;
	}
	
	private $_name;
	private $_inputs;
	private $_senses;

	public function __construct($name) {

		$this->__dispatchesConstruct();

		$this->_name = Dev::apply('sensory.inputarray.name', $name);
		$this->_inputs = [];
		$this->_senses = [];

		Dev::do('sensory.inputarray.construct', ['name' => $this->_name]);
	}

	public function create( $label, $processors = [] )
	{
        $label = Dev::apply('sensory.inputarray.create_label', $label);
        $processors = Dev::apply('sensory.inputarray.create_processors', $processors);
		$input = new Input();

		$input->name($label);
		foreach ($processors as $processor) {
			$input->setProcessor( $processor );
		}
		$input->behavior(Event::COMPLETE, [$this, 'onInputComplete']);

		switch( $label ) {
			default:
			case InputType::TEXT:
				$sense = new Sense();
			break;
			case InputType::IMAGE:
				$sense = new Sense();
			break;
			case InputType::AUDIO:
				$sense = new Sense();
			break;
			case InputType::VIDEO:
				$sense = new Sense();
			break;
		}

		$sense->behavior(Event::COMPLETE, [$this, 'onParseComplete']);
		$sense->behavior(Event::SUCCESS, [$this, 'onParseSuccess']);

		$this->_inputs[$label] = $input;
		$this->_senses[$label] = $sense;
        Dev::do('sensory.inputarray.created', ['label' => $label, 'input' => $input, 'sense' => $sense]);
	}

	public function observe( $package )
	{
        $package = Dev::apply('sensory.inputarray.observe_package', $package);
		foreach ($package as $key=>$data ) {
			$this->read($data, $key);
		}
        Dev::do('sensory.inputarray.observe', ['package' => $package]);
	}

	public function read( $data, $type = null )
	{
        $data = Dev::apply('sensory.inputarray.read_data', $data);
		$type = $type ?? $this->detect($data);

		if ( isset($this->_inputs[$type]) ) {
			$this->_inputs[$type]->scan($data);
		} else {
			current($this->_inputs)->scan($data);
		}

		Dev::do('sensory.inputarray.read', ['type' => $type, 'data' => $data]);
	}

	public function parse($data, $type = null) 
	{
		$type = $type ?? $this->detect($data);
	
		Dev::do('sensory.inputarray.invoke', ['type' => $type, 'data' => $data]);
		$this->_senses[$type]->invoke($data);
	}

	public function process()
	{
        Dev::do('sensory.inputarray.process_start', ['queue' => $this->_name]);
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
        Dev::do('sensory.inputarray.process_complete', ['count' => $count]);
	}

	public function reset()
	{
        Dev::do('sensory.inputarray.reset_start', []);
		foreach( $this->_senses as $sense ) {
			$sense->reset();
		}
        Dev::do('sensory.inputarray.reset', []);
	}

	public function detect( $data )
	{
		return InputType::TEXT;
	}

	public function onInputComplete( $behavior )
	{
		Queue::enqueue( $this->_name, [$behavior->target->name(), $behavior->context] );
        Dev::do('sensory.inputarray.input_complete', ['behavior' => $behavior]);
	}

	public function onParseSuccess( $behavior, $data )
	{
        Dev::do('sensory.inputarray.parse_success', ['behavior' => $behavior, 'data' => $data]);
		$this->dispatch($behavior, $data[0]);
	}

	public function onParseComplete( $behavior, $data )
	{
        Dev::do('sensory.inputarray.parse_complete', ['behavior' => $behavior, 'data' => $data]);
		$this->dispatch($behavior, $data[0]);
	}
}
