<?php
namespace BlueFission;

use BlueFission\Behavioral\Behaviors\Event;

/**
 * Class Pile
 * This class is a value object for Stacks.
 * It encapsulates PHP's DS\Stack and provides an interface compliant with IVal.
 *
 * @package BlueFission
 * @implements IVal
 */
class Pile extends Val implements IVal {
    protected $_type = DataTypes::GENERIC;

    protected $_forceType = false;

    /**
     * Pile constructor.
     * Initialize the Stack.
     * @param mixed $value Initial elements for the Stack
     * @param bool $snapshot Whether to take a snapshot after initialization
     */
    public function __construct($value = null, bool $snapshot = true) {
        parent::__construct($this->buildStorage($value), $snapshot, false);
    }

    /**
     * Override the cast method to ensure the data is a Stack.
     * @return IVal
     */
    public function cast(): IVal {
        if ($this->supportsDs()) {
            if (!$this->isDsStack($this->_data)) {
                $this->_data = $this->buildStorage($this->_data);
            }
            return $this;
        }

        if (!is_array($this->_data)) {
            $this->_data = Arr::toArray($this->_data);
        }

        return $this;
    }

    /**
     * Check if value is a Stack
     * @return bool
     */
    public function _is(): bool {
        return $this->isDsStack($this->_data) || is_array($this->_data);
    }

    /**
     * Add an element to the top of the Stack
     * @param mixed $value The element to add
     * @return IVal The current instance for chaining
     */
    public function push($value): IVal {
        if ($this->isDsStack($this->_data)) {
            $this->_data->push($value);
        } else {
            $data = Arr::make($this->_data);
            $data->push($value);
            $this->_data = $data->val();
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Remove and return the top element of the Stack
     * @return mixed The element at the top of the Stack
     */
    public function pop() {
        if ($this->isDsStack($this->_data)) {
            if (!$this->_data->isEmpty()) {
                return $this->_data->pop();
            }
            return null;
        }

        if (!Flag::isEmpty($this->_data)) {
            return Arr::pop($this->_data);
        }

        return null;
    }

    /**
     * Peek at the top element of the Stack without removing it
     * @return mixed The element at the top if available
     */
    public function peek() {
        if ($this->isDsStack($this->_data)) {
            if (!$this->_data->isEmpty()) {
                return $this->_data->peek();
            }
            return null;
        }

        if (!Flag::isEmpty($this->_data)) {
            return $this->_data[Arr::count($this->_data) - 1] ?? null;
        }

        return null;
    }

    /**
     * Check if the Stack is empty
     * @return bool True if the stack is empty, false otherwise
     */
    public function isEmpty(): bool {
        if ($this->isDsStack($this->_data)) {
            return $this->_data->isEmpty();
        }

        return Flag::isEmpty($this->_data);
    }

    /**
     * Clear all elements in the Stack
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        if ($this->isDsStack($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the Stack
     * @return int The count of elements
     */
    public function count(): int {
        if ($this->isDsStack($this->_data)) {
            return $this->_data->count();
        }

        return Arr::count($this->_data);
    }

    protected function supportsDs(): bool
    {
        return class_exists('\Ds\Stack');
    }

    protected function isDsStack(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\Stack;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $items = Arr::toArray($seed);

        if ($this->supportsDs()) {
            $stack = new \Ds\Stack();
            foreach ($items as $item) {
                $stack->push($item);
            }
            return $stack;
        }

        return $items;
    }
}
