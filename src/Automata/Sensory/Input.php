<?php

namespace BlueFission\Automata\Sensory;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Behavior;
use BlueFission\Behavioral\Dispatcher;
use BlueFission\Collections\Collection;
use BlueFission\Str;

/**
 * Input class handles the processing of input data through a series of processors.
 * It extends Dispatcher to utilize event-driven behavior.
 */
class Input extends Dispatcher
{
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
        parent::__construct();

        // If no processor is provided, use a default processor that returns the data as is
        if (!$processor) {
            $processor = function($data) {
                return $data;
            };
        }

        $this->_processors = new Collection();
        $this->_processors[] = $processor;
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
            $this->_processors[] = $processor;
        }

        // Process the data through all processors
        foreach ($this->_processors as $processor) {
            // Apply the processor function to the data
            $data = call_user_func_array($processor, [$data]);
        }

        // Dispatch a complete event with the processed data
        $this->dispatch(Event::COMPLETE, $data);
    }

    /**
     * Dispatches a behavior event.
     *
     * @param mixed $behavior The behavior to dispatch. Can be a string or a Behavior object.
     * @param mixed|null $args Optional arguments to pass with the behavior.
     */
    public function dispatch($behavior, $args = null)
    {
        // If the behavior is a string, create a new Behavior object
        if (Str::is($behavior)) {
            $behavior = new Behavior($behavior);
            $behavior->_target = $this;
        }

        // If the behavior's target is this input, set the context and clear the args
        if ($behavior->_target == $this) {
            $behavior->_context = $args;
            $args = null;
        }

        // Call the parent dispatch method
        parent::dispatch($behavior, $args);
    }

    /**
     * Initializes the Input object.
     */
    protected function init()
    {
        parent::init();
    }
}