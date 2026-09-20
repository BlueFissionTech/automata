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

/**
 * Named Input/Sense pairs joined by a MemQueue-backed processing stage.
 *
 * read()/observe() run Input processors and enqueue their completion payloads;
 * process() drains that queue and invokes Sense. parse() bypasses the queue.
 * Queue operations require the upstream MemQueue runtime, including ext-memcached.
 * Registration alone does not exercise or prove queue availability.
 *
 * This legacy coordinator has no Experience projection, acknowledgement/retry
 * protocol, or modality-specific decoding. Applications own those boundaries.
 */
class InputArray implements IDispatcher {
	use Dispatches {
		Dispatches::__construct as private __dispatchesConstruct;
	}
	
	/** @var string Queue namespace shared by producers and process(). */
	private $_name;
	/** @var array<string, Input> Processors indexed by registration label. */
	private $_inputs;
	/** @var array<string, Sense> Corresponding analysis instances. */
	private $_senses;

	/** Initialize local registrations; no queue connection is tested here. */
	public function __construct($name) {

		$this->__dispatchesConstruct();

		$this->_name = Dev::apply('sensory.inputarray.name', $name);
		$this->_inputs = [];
		$this->_senses = [];

		Dev::do('sensory.inputarray.construct', ['name' => $this->_name]);
	}

	/**
	 * Register a processing chain and its Sense, replacing an existing label.
	 *
	 * All modality labels currently select the same Sense implementation. Image,
	 * audio and video labels therefore do not promise decoding of those formats.
	 * Processors must supply data suitable for Sense's preparation callback.
	 *
	 * @param string $label Routing key, conventionally an InputType constant.
	 * @param iterable<callable> $processors Ordered transformations.
	 * @return void
	 */
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

	/** Submit each label => payload entry through read(); analysis is deferred. */
	public function observe( $package )
	{
        $package = Dev::apply('sensory.inputarray.observe_package', $package);
		foreach ($package as $key=>$data ) {
			$this->read($data, $key);
		}
        Dev::do('sensory.inputarray.observe', ['package' => $package]);
	}

	/**
	 * Process and enqueue one payload; callers invoke process() separately.
	 *
	 * Unknown types fall back to the current registered Input. At least one
	 * registration is required; an empty registry is not handled gracefully.
	 */
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

	/**
	 * Invoke a registered Sense directly, without Input processors or queuing.
	 * Unlike read(), this requires the resolved type to exist in the registry.
	 * The Sense return value is discarded; delivery uses its event listeners.
	 */
	public function parse($data, $type = null)
	{
		$type = $type ?? $this->detect($data);
	
		Dev::do('sensory.inputarray.invoke', ['type' => $type, 'data' => $data]);
		$this->_senses[$type]->invoke($data);
	}

	/**
	 * Drain queued [label, payload] pairs and reset senses before each parse.
	 *
	 * The 10,000 limit counts parsed array entries, not malformed dequeues, time,
	 * or bytes. Arrays are not shape-validated. Exceptions propagate after dequeue;
	 * this implementation does not requeue or retain a recovery receipt.
	 */
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

	/** Reset each Sense's settings/map/depth; leave registrations and queue intact. */
	public function reset()
	{
        Dev::do('sensory.inputarray.reset_start', []);
		foreach( $this->_senses as $sense ) {
			$sense->reset();
		}
        Dev::do('sensory.inputarray.reset', []);
	}

	/**
	 * Legacy text-only default, independent of Automata's InputTypeDetector.
	 * Callers must pass a registered type explicitly for other routing labels.
	 */
	public function detect( $data )
	{
		return InputType::TEXT;
	}

	/** Enqueue the emitting Input's name and COMPLETE context as a routing pair. */
	public function onInputComplete( $behavior )
	{
		Queue::enqueue( $this->_name, [$behavior->target->name(), $behavior->context] );
        Dev::do('sensory.inputarray.input_complete', ['behavior' => $behavior]);
	}

	/**
	 * Legacy SUCCESS relay expecting a positional payload whose first item is data.
	 * Sense's context-based dispatch contract needs reconciliation with this
	 * two-argument callback before the full queued path can be considered proven.
	 */
	public function onParseSuccess( $behavior, $data )
	{
        Dev::do('sensory.inputarray.parse_success', ['behavior' => $behavior, 'data' => $data]);
		$this->dispatch($behavior, $data[0]);
	}

	/** Relay COMPLETE using the same legacy positional contract as onParseSuccess(). */
	public function onParseComplete( $behavior, $data )
	{
        Dev::do('sensory.inputarray.parse_complete', ['behavior' => $behavior, 'data' => $data]);
		$this->dispatch($behavior, $data[0]);
	}
}
