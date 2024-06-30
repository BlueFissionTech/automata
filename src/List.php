<?php
namespace BlueFission;

use Ds\Set;
use BlueFission\Behavioral\Behaviors\Event;

/**
 * Class List
 * This class is a value object for Sets.
 * It encapsulates PHP's DS\Set and provides an interface compliant with IVal.
 *
 * @package BlueFission
 * @implements IVal
 */
class List extends Val implements IVal {
    protected $_type = DataTypes::SET;

    protected $_forceType = false;

    /**
     * List constructor.
     * Initialize the Set with elements if provided.
     * @param mixed $value Initial elements for the Set
     * @param bool $snapshot Whether to take a snapshot after initialization
     */
    public function __construct($value = null, bool $snapshot = true) {
        parent::__construct(new Set($value), $snapshot, false);
    }

    /**
     * Override the cast method to ensure the data is a Set.
     * @return IVal
     */
    public function cast(): IVal {
        if (!($this->_data instanceof Set)) {
            $this->_data = new Set($this->_data);
        }
        return $this;
    }

    /**
     * Check if value is a Set
     * @return bool
     */
    public function _is(): bool {
        return $this->_data instanceof Set;
    }

    /**
     * Add an element to the Set
     * @param mixed $value The element to add
     * @return IVal The current instance for chaining
     */
    public function add($value): IVal {
        $this->_data->add($value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Remove an element from the Set
     * @param mixed $value The element to remove
     * @return IVal The current instance for chaining
     */
    public function remove($value): IVal {
        $this->_data->remove($value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Check if the Set contains a specific element
     * @param mixed $value The element to check for
     * @return bool True if the element exists, false otherwise
     */
    public function has($value): bool {
        return $this->_data->contains($value);
    }

    /**
     * Clear all elements in the Set
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        $this->_data->clear();
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the Set
     * @return int The count of elements
     */
    public function count(): int {
        return $this->_data->count();
    }

    /**
     * Get the union of this Set with another Set
     * @param Set $set The set to union with
     * @return Set The union of both sets
     */
    public function union(Set $set): Set {
        return $this->_data->union($set);
    }

    /**
     * Get the intersection of this Set with another Set
     * @param Set $set The set to intersect with
     * @return Set The intersection of both sets
     */
    public function intersect(Set $set): Set {
        return $this->_data->intersect($set);
    }

    /**
     * Get the difference of this Set with another Set
     * @param Set $set The set to differentiate with
     * @return Set The difference of both sets
     */
    public function diff(Set $set): Set {
        return $this->_data->diff($set);
    }
}
