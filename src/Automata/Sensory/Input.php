<?php

namespace BlueFission\Automata\Sensory;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Behavior;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Collections\Collection;
use BlueFission\Str;
use BlueFission\DevElation as Dev;
use InvalidArgumentException;

/**
 * Synchronous, ordered input transformations with event-based output delivery.
 *
 * Each processor receives the preceding processor's result. The final value is
 * delivered as Event::COMPLETE context; scan() does not return that value.
 * Dispatches supplies the dispatcher implementation without a base class.
 * This stage does not infer meaning, create an Experience, or authorize input.
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

        if ($processor === null) {
            $processor = function($data) {
                return $data;
            };
        }

        $this->_processors = new Collection();
        $this->setProcessor(Dev::apply('sensory.input.processor', $processor));
        Dev::do('sensory.input.construct', ['processor' => $processor, 'instance' => $this]);
    }

    /**
     * Sets or gets the name of the input source.
     *
     * Null/empty string reads the existing name. A nonempty string, including
     * "0", sets it and returns this instance for fluent registration.
     *
     * @param string $name Optional name to set.
     * @return string|null|$this The existing name in getter mode, otherwise this instance.
     */
    public function name($name = '')
    {
        if ($name === '' || $name === null) {
            return $this->_name;
        }
        if (!is_string($name)) {
            throw new InvalidArgumentException('Input name must be a string.');
        }
        $this->_name = $name;
        return $this;
    }

    /**
     * Adds a processor function to the list of processors.
     *
     * Despite the setter name, this appends; it does not replace prior stages.
     * Callability is checked before registration. Returns this instance.
     *
     * @param callable $processorFunction The processor function to add.
     */
    public function setProcessor($processorFunction)
    {
        if (!is_callable($processorFunction)) {
            throw new InvalidArgumentException('Input processor must be callable.');
        }
        $this->_processors[] = $processorFunction;
        return $this;
    }

    /**
     * Processes the input data through all registered processors and dispatches a complete event.
     *
     * The optional processor remains registered for subsequent scans. Failures
     * propagate to the caller; a failed processor prevents the completion event.
     * Consumers must subscribe before scanning to receive this synchronous event.
     * Returns this instance, while processed output remains in the event context.
     *
     * @param mixed $data The input data to process.
     * @param callable|null $processor Optional additional processor function.
     */
    public function scan($data, $processor = null)
    {
        // Registration is persistent, including processors supplied for this scan.
        if ($processor !== null) {
            $this->setProcessor(Dev::apply('sensory.input.extra_processor', $processor));
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
        return $this;
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

        // Use the aliased trait method to avoid recursively calling this override.
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
