<?php
namespace BlueFission;

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
        parent::__construct($this->buildStorage($value), $snapshot, false);
    }

    /**
     * Override the cast method to ensure the data is a PriorityQueue.
     * @return IVal
     */
    public function cast(): IVal {
        if ($this->supportsDs()) {
            if (!$this->isDsPriorityQueue($this->_data)) {
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
     * Check if value is a PriorityQueue
     * @return bool
     */
    public function _is(): bool {
        return $this->isDsPriorityQueue($this->_data) || is_array($this->_data);
    }

    /**
     * Add an element with priority to the PriorityQueue
     * @param mixed $value The element to add
     * @param int $priority The priority of the element
     * @return IVal The current instance for chaining
     */
    public function insert($value, int $priority): IVal {
        if ($this->isDsPriorityQueue($this->_data)) {
            $this->_data->push($value, $priority);
        } else {
            $data = Arr::make($this->_data);
            $data->push([$value, $priority]);
            $this->_data = $data->val();
            $this->sortFallback();
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Remove and return the highest priority element from the PriorityQueue
     * @return mixed The highest priority element
     */
    public function extract() {
        if ($this->isDsPriorityQueue($this->_data)) {
            if (!$this->_data->isEmpty()) {
                return $this->_data->pop();
            }
            return null;
        }

        if (!Arr::isEmpty($this->_data)) {
            $data = Arr::make($this->_data);
            $entry = $data->shift();
            $this->_data = $data->val();
            return is_array($entry) ? ($entry[0] ?? null) : null;
        }

        return null;
    }

    /**
     * Peek at the highest priority element without removing it
     * @return mixed The highest priority element if available
     */
    public function peek() {
        if ($this->isDsPriorityQueue($this->_data)) {
            if (!$this->_data->isEmpty()) {
                return $this->_data->peek();
            }
            return null;
        }

        if (!Arr::isEmpty($this->_data)) {
            $entry = $this->_data[0] ?? null;
            return is_array($entry) ? ($entry[0] ?? null) : null;
        }

        return null;
    }

    /**
     * Check if the PriorityQueue is empty
     * @return bool True if the queue is empty, false otherwise
     */
    public function isEmpty(): bool {
        if ($this->isDsPriorityQueue($this->_data)) {
            return $this->_data->isEmpty();
        }

        return Arr::isEmpty($this->_data);
    }

    /**
     * Clear all elements in the PriorityQueue
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        if ($this->isDsPriorityQueue($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the PriorityQueue
     * @return int The count of elements
     */
    public function count(): int {
        if ($this->isDsPriorityQueue($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    protected function supportsDs(): bool
    {
        return class_exists('\Ds\PriorityQueue');
    }

    protected function isDsPriorityQueue(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\PriorityQueue;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $items = Arr::toArray($seed);

        if ($this->supportsDs()) {
            $queue = new \Ds\PriorityQueue();
            foreach ($items as $item) {
                if (is_array($item) && Arr::size($item) === 2) {
                    $queue->push($item[0], $item[1]);
                }
            }
            return $queue;
        }

        $queue = [];
        foreach ($items as $item) {
            if (is_array($item) && Arr::size($item) === 2) {
                $queue[] = [$item[0], $item[1]];
            }
        }
        $this->_data = $queue;
        $this->sortFallback();
        return $this->_data;
    }

    protected function sortFallback(): void
    {
        if (!is_array($this->_data)) {
            return;
        }

        $collection = new \BlueFission\Collections\Collection($this->_data);
        $this->_data = $collection->sort(
            function ($left, $right) {
                $leftPriority = $left[1] ?? 0;
                $rightPriority = $right[1] ?? 0;

                return $rightPriority <=> $leftPriority;
            }
        )->contents();
    }
}
