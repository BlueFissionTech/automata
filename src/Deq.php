<?php
namespace BlueFission;

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
        parent::__construct($this->buildStorage($seed), $snapshot, false);
    }

    /**
     * Override the cast method to ensure the data is a Deque.
     * @return IVal
     */
    public function cast(): IVal {
        if ($this->supportsDs()) {
            if (!$this->isDsDeque($this->_data)) {
                $this->_data = $this->buildStorage($this->toArray());
            }
            return $this;
        }

        if (!is_array($this->_data)) {
            $this->_data = Arr::toArray($this->_data);
        }

        return $this;
    }

    /**
     * Check if value is a Deque
     * @return bool
     */
    public function _is(): bool {
        return $this->isDsDeque($this->_data) || is_array($this->_data);
    }

    /**
     * Add a value to the front of the Deque
     * @param mixed $value The value to add
     * @return IVal The current instance for chaining
     */
    public function pushFront($value): IVal {
        if ($this->isDsDeque($this->_data)) {
            $this->_data->unshift($value);
        } else {
            $data = Arr::make($this->_data);
            $data->unshift($value);
            $this->_data = $data->val();
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Add a value to the back of the Deque
     * @param mixed $value The value to add
     * @return IVal The current instance for chaining
     */
    public function pushBack($value): IVal {
        if ($this->isDsDeque($this->_data)) {
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
     * Remove a value from the front of the Deque
     * @return mixed The value removed from the front
     */
    public function popFront() {
        if ($this->isDsDeque($this->_data)) {
            $value = $this->_data->shift();
        } else {
            $data = Arr::make($this->_data);
            $value = $data->shift();
            $this->_data = $data->val();
        }
        $this->trigger(Event::CHANGE);
        return $value;
    }

    /**
     * Remove a value from the back of the Deque
     * @return mixed The value removed from the back
     */
    public function popBack() {
        if ($this->isDsDeque($this->_data)) {
            $value = $this->_data->pop();
        } else {
            $data = Arr::make($this->_data);
            $value = $data->pop();
            $this->_data = $data->val();
        }
        $this->trigger(Event::CHANGE);
        return $value;
    }

    /**
     * Get the element at the specified index
     * @param int $index The index of the element
     * @return mixed The element at the specified index
     */
    public function get(int $index) {
        if ($this->isDsDeque($this->_data)) {
            return $this->_data->get($index);
        }

        return $this->_data[$index] ?? null;
    }

    /**
     * Set the element at the specified index
     * @param int $index The index where the element should be set
     * @param mixed $value The value to set at the index
     * @return IVal The current instance for chaining
     */
    public function set(int $index, $value): IVal {
        if ($this->isDsDeque($this->_data)) {
            $this->_data->set($index, $value);
        } else {
            $this->_data[$index] = $value;
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Clear all elements in the Deque
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        if ($this->isDsDeque($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the Deque
     * @return int The count of elements
     */
    public function count(): int {
        if ($this->isDsDeque($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    /**
     * Check if the Deque is empty.
     */
    public function isEmpty(): bool
    {
        if ($this->isDsDeque($this->_data)) {
            return $this->_data->isEmpty();
        }

        return Arr::isEmpty($this->_data);
    }

    protected function supportsDs(): bool
    {
        return class_exists('\Ds\Deque');
    }

    protected function isDsDeque(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\Deque;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $data = Arr::toArray($seed);

        if ($this->supportsDs()) {
            return new \Ds\Deque($data);
        }

        return $data;
    }

    protected function toArray(): array
    {
        if ($this->isDsDeque($this->_data)) {
            return $this->_data->toArray();
        }

        return Arr::toArray($this->_data);
    }
}
