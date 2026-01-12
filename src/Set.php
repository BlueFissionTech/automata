<?php

namespace BlueFission;

use Ds\Set as DsSet;
use BlueFission\Behavioral\Behaviors\Event;

/**
 * Class Set
 *
 * Value object wrapper around Ds\Set, intended as a high-performance
 * set/list structure for large and streaming datasets.
 */
class Set extends Val implements IVal {
    protected $_type = DataTypes::GENERIC;

    protected $_forceType = false;

    /**
     * Set constructor.
     *
     * @param mixed $value    Initial elements for the set
     * @param bool  $snapshot Whether to take a snapshot after initialization
     */
    public function __construct($value = null, bool $snapshot = true) {
        $seed = $value ?? [];
        parent::__construct(new DsSet($seed), $snapshot, false);
    }

    /**
     * Ensure the underlying data is a Ds\Set.
     */
    public function cast(): IVal {
        if (!($this->_data instanceof DsSet)) {
            $seed = $this->_data ?? [];
            $this->_data = new DsSet($seed);
        }
        return $this;
    }

    /**
     * Check if value is a set.
     */
    public function _is(): bool {
        return $this->_data instanceof DsSet;
    }

    /**
     * Add an element to the set.
     */
    public function add($value): IVal {
        $this->_data->add($value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Remove an element from the set.
     */
    public function remove($value): IVal {
        $this->_data->remove($value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Check if the set contains a specific element.
     */
    public function has($value): bool {
        return $this->_data->contains($value);
    }

    /**
     * Clear all elements in the set.
     */
    public function clear(): IVal {
        $this->_data->clear();
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the set.
     */
    public function count(): int {
        return $this->_data->count();
    }

    /**
     * Get the union of this set with another.
     */
    public function union(DsSet $set): DsSet {
        return $this->_data->union($set);
    }

    /**
     * Get the intersection of this set with another.
     */
    public function intersect(DsSet $set): DsSet {
        return $this->_data->intersect($set);
    }

    /**
     * Get the difference of this set with another.
     */
    public function diff(DsSet $set): DsSet {
        return $this->_data->diff($set);
    }
}

