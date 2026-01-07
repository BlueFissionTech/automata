<?php
namespace BlueFission;

use Ds\Stack;
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
        parent::__construct(new Stack(), $snapshot, false);

        if (is_array($value)) {
            foreach (array_reverse($value) as $item) { // reverse to maintain push order
                $this->_data->push($item);
            }
        }
    }

    /**
     * Override the cast method to ensure the data is a Stack.
     * @return IVal
     */
    public function cast(): IVal {
        if (!($this->_data instanceof Stack)) {
            $tempStack = new Stack();
            foreach ($this->_data as $item) {
                $tempStack->push($item);
            }
            $this->_data = $tempStack;
        }
        return $this;
    }

    /**
     * Check if value is a Stack
     * @return bool
     */
    public function _is(): bool {
        return $this->_data instanceof Stack;
    }

    /**
     * Add an element to the top of the Stack
     * @param mixed $value The element to add
     * @return IVal The current instance for chaining
     */
    public function push($value): IVal {
        $this->_data->push($value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Remove and return the top element of the Stack
     * @return mixed The element at the top of the Stack
     */
    public function pop() {
        if (!$this->_data->isEmpty()) {
            return $this->_data->pop();
        }
        return null;
    }

    /**
     * Peek at the top element of the Stack without removing it
     * @return mixed The element at the top if available
     */
    public function peek() {
        if (!$this->_data->isEmpty()) {
            return $this->_data->peek();
        }
        return null;
    }

    /**
     * Check if the Stack is empty
     * @return bool True if the stack is empty, false otherwise
     */
    public function isEmpty(): bool {
        return $this->_data->isEmpty();
    }

    /**
     * Clear all elements in the Stack
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        $this->_data->clear();
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the Stack
     * @return int The count of elements
     */
    public function count(): int {
        return $this->_data->count();
    }
}
