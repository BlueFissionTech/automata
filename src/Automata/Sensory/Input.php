<?php

namespace BlueFission\Automata\Sensory;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Behavior;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Collections\Collection;
use BlueFission\Str;
use BlueFission\DevElation as Dev;

/**
 * Input class handles the processing of input data through a series of processors.
 * It extends Dispatcher to utilize event-driven behavior.
 */
class Input implements IDispatcher
{
    use Dispatches {
        Dispatches::__construct as private __dispatchesConstruct;
        Dispatches::dispatch as private __dispatchFromTrait;
    }

    /**
     * @var Collection $_processors Collection of processors to process the input data.
     */
    protected $_processors;

    /**
     * @var string $_name Name of the input source.
     */
    protected $_name;

    /**
     * Constructor initializes the input object with an optional processor.
     *
     * @param callable|null $processor Optional processor function to process input data.
     */
    public function __construct($processor = null)
    {
        $this->__dispatchesConstruct();

        if (!$processor) {
            $processor = function($data) {
                return $data;
            };
        }

        $this->_processors = new Collection();
        $this->_processors[] = Dev::apply('sensory.input.processor', $processor);
        Dev::do('sensory.input.construct', ['processor' => $processor, 'instance' => $this]);
    }

    /**
     * Sets or gets the name of the input source.
     *
     * @param string $name Optional name to set.
     * @return string|null The name of the input source if no name is provided to set.
     */
    public function name($name = '')
    {
        if (!$name) {
            return $this->_name;
        }
        $this->_name = $name;
    }

    /**
     * Adds a processor function to the list of processors.
     *
     * @param callable $processorFunction The processor function to add.
     */
    public function setProcessor($processorFunction)
    {
        $this->_processors[] = $processorFunction;
    }

    /**
     * Processes the input data through all registered processors and dispatches a complete event.
     *
     * @param mixed $data The input data to process.
     * @param callable|null $processor Optional additional processor function.
     */
    public function scan($data, $processor = null)
    {
        // Add the additional processor function if provided
        if ($processor) {
            $this->_processors[] = Dev::apply('sensory.input.extra_processor', $processor);
        }

        // Process the data through all processors
        foreach ($this->_processors as $processor) {
            // Apply the processor function to the data
            $data = Dev::apply('sensory.input.processor.apply', call_user_func_array($processor, [$data]));
        }

        // Dispatch a complete event with the processed data
        $data = Dev::apply('sensory.input.scan_result', $data);
        $this->dispatch(Event::COMPLETE, $data);
        Dev::do('sensory.input.scan', ['data' => $data]);
    }

    /**
     * Dispatches a behavior event.
     *
     * @param mixed $behavior The behavior to dispatch. Can be a string or a Behavior object.
     * @param mixed|null $args Optional arguments to pass with the behavior.
     */
    public function dispatch($behavior, $args = null): IDispatcher
    {
        // If the behavior is a string, create a new Behavior object
        if (Str::is($behavior)) {
            $behavior = new Behavior($behavior);
            $behavior->target = $this;
        }

        // If the behavior's target is this input, set the context and clear the args
        if ($behavior->target == $this) {
            $behavior->context = $args;
            $args = null;
        }

        // Call the parent dispatch method
        return $this->__dispatchFromTrait($behavior, $args);
    }

    /**
     * Initializes the Input object.
     */
    protected function init()
    {
        // Dispatches trait handles base setup; nothing additional for Input.
    }
}
