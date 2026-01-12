<?php
namespace BlueFission;

use Ds\Deque;
use BlueFission\Behavioral\Behaviors\Event;

/**
 * Class Deq
 * This class is a value object for Deques.
 * It encapsulates PHP's DS\Deque and provides an interface compliant with IVal.
 *
 * @package BlueFission
 * @implements IVal
 */
class Deq extends Val implements IVal {
    protected $_type = DataTypes::GENERIC;

    protected $_forceType = false;

    /**
     * Deq constructor.
     * Initialize the Deque with values if provided.
     * @param mixed $value Initial values for the Deque
     * @param bool $snapshot Whether to take a snapshot after initialization
     */
    public function __construct($value = null, bool $snapshot = true) {
        $seed = $value ?? [];
        parent::__construct(new Deque($seed), $snapshot, false);
    }

    /**
     * Override the cast method to ensure the data is a Deque.
     * @return IVal
     */
    public function cast(): IVal {
        if (!($this->_data instanceof Deque)) {
            $seed = $this->_data ?? [];
            $this->_data = new Deque($seed);
        }
        return $this;
    }

    /**
     * Check if value is a Deque
     * @return bool
     */
    public function _is(): bool {
        return $this->_data instanceof Deque;
    }

    /**
     * Add a value to the front of the Deque
     * @param mixed $value The value to add
     * @return IVal The current instance for chaining
     */
    public function pushFront($value): IVal {
        $this->_data->unshift($value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Add a value to the back of the Deque
     * @param mixed $value The value to add
     * @return IVal The current instance for chaining
     */
    public function pushBack($value): IVal {
        $this->_data->push($value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Remove a value from the front of the Deque
     * @return mixed The value removed from the front
     */
    public function popFront() {
        $value = $this->_data->shift();
        $this->trigger(Event::CHANGE);
        return $value;
    }

    /**
     * Remove a value from the back of the Deque
     * @return mixed The value removed from the back
     */
    public function popBack() {
        $value = $this->_data->pop();
        $this->trigger(Event::CHANGE);
        return $value;
    }

    /**
     * Get the element at the specified index
     * @param int $index The index of the element
     * @return mixed The element at the specified index
     */
    public function get(int $index) {
        return $this->_data->get($index);
    }

    /**
     * Set the element at the specified index
     * @param int $index The index where the element should be set
     * @param mixed $value The value to set at the index
     * @return IVal The current instance for chaining
     */
    public function set(int $index, $value): IVal {
        $this->_data->set($index, $value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Clear all elements in the Deque
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        $this->_data->clear();
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the Deque
     * @return int The count of elements
     */
    public function count(): int {
        return $this->_data->count();
    }

    /**
     * Check if the Deque is empty.
     */
    public function isEmpty(): bool
    {
        return $this->_data->isEmpty();
    }
}
