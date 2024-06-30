<?php
namespace BlueFission;

use Ds\Vector;
use BlueFission\Behavioral\Behaviors\Event;

/**
 * Class Vec
 * This class is a value object for Vectors.
 * It encapsulates PHP's DS\Vector and provides an interface compliant with IVal.
 *
 * @package BlueFission
 * @implements IVal
 */
class Vec extends Val implements IVal {
    protected $_type = DataTypes::VECTOR;

    protected $_forceType = false;

    /**
     * Vec constructor.
     * Initialize the Vector with values if provided.
     * @param mixed $value Initial values for the Vector
     * @param bool $snapshot Whether to take a snapshot after initialization
     */
    public function __construct($value = null, bool $snapshot = true) {
        parent::__construct(new Vector($value), $snapshot, false);
    }

    /**
     * Override the cast method to ensure the data is a Vector.
     * @return IVal
     */
    public function cast(): IVal {
        if (!($this->_data instanceof Vector)) {
            $this->_data = new Vector($this->_data);
        }
        return $this;
    }

    /**
     * Check if value is a Vector
     * @return bool
     */
    public function _is(): bool {
        return $this->_data instanceof Vector;
    }

    /**
     * Add a value to the Vector
     * @param mixed $value The value to add
     * @return IVal The current instance for chaining
     */
    public function add($value): IVal {
        $this->_data->push($value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Remove a value from the Vector by index
     * @param int $index The index to remove
     * @return IVal The current instance for chaining
     */
    public function remove(int $index): IVal {
        if (isset($this->_data[$index])) {
            $this->_data->remove($index);
            $this->trigger(Event::CHANGE);
        }
        return $this;
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
     * Clear all elements in the Vector
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        $this->_data->clear();
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the Vector
     * @return int The count of elements
     */
    public function count(): int {
        return $this->_data->count();
    }
}
