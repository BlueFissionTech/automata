<?php
namespace BlueFission;

use Ds\PriorityQueue;
use BlueFission\Behavioral\Behaviors\Event;

/**
 * Class Pri
 * This class is a value object for Priority Queues.
 * It encapsulates PHP's DS\PriorityQueue and provides an interface compliant with IVal.
 *
 * @package BlueFission
 * @implements IVal
 */
class Pri extends Val implements IVal {
    protected $_type = DataTypes::GENERIC;

    protected $_forceType = false;

    /**
     * Pri constructor.
     * Initialize the PriorityQueue with elements if provided.
     * @param mixed $value Initial elements for the PriorityQueue
     * @param bool $snapshot Whether to take a snapshot after initialization
     */
    public function __construct($value = null, bool $snapshot = true) {
        parent::__construct(new PriorityQueue(), $snapshot, false);

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item) && count($item) === 2) {
                    $this->_data->push($item[0], $item[1]);
                }
            }
        }
    }

    /**
     * Override the cast method to ensure the data is a PriorityQueue.
     * @return IVal
     */
    public function cast(): IVal {
        if (!($this->_data instanceof PriorityQueue)) {
            $tempQueue = new PriorityQueue();
            foreach ($this->_data as $item) {
                if (is_array($item) && count($item) === 2) {
                    $tempQueue->push($item[0], $item[1]);
                }
            }
            $this->_data = $tempQueue;
        }
        return $this;
    }

    /**
     * Check if value is a PriorityQueue
     * @return bool
     */
    public function _is(): bool {
        return $this->_data instanceof PriorityQueue;
    }

    /**
     * Add an element with priority to the PriorityQueue
     * @param mixed $value The element to add
     * @param int $priority The priority of the element
     * @return IVal The current instance for chaining
     */
    public function insert($value, int $priority): IVal {
        $this->_data->push($value, $priority);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Remove and return the highest priority element from the PriorityQueue
     * @return mixed The highest priority element
     */
    public function extract() {
        if (!$this->_data->isEmpty()) {
            return $this->_data->pop();
        }
        return null;
    }

    /**
     * Peek at the highest priority element without removing it
     * @return mixed The highest priority element if available
     */
    public function peek() {
        if (!$this->_data->isEmpty()) {
            return $this->_data->peek();
        }
        return null;
    }

    /**
     * Check if the PriorityQueue is empty
     * @return bool True if the queue is empty, false otherwise
     */
    public function isEmpty(): bool {
        return $this->_data->isEmpty();
    }

    /**
     * Clear all elements in the PriorityQueue
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        $this->_data->clear();
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the PriorityQueue
     * @return int The count of elements
     */
    public function count(): int {
        return $this->_data->count();
    }
}
