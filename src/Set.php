<?php

namespace BlueFission;

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
        parent::__construct($this->buildStorage($seed), $snapshot, false);
    }

    /**
     * Ensure the underlying data is a Ds\Set.
     */
    public function cast(): IVal {
        if ($this->supportsDs()) {
            if (!$this->isDsSet($this->_data)) {
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
     * Check if value is a set.
     */
    public function _is(): bool {
        return $this->isDsSet($this->_data) || is_array($this->_data);
    }

    /**
     * Add an element to the set.
     */
    public function add($value): IVal {
        if ($this->isDsSet($this->_data)) {
            $this->_data->add($value);
        } elseif (!Arr::has($this->_data, $value, true)) {
            $data = Arr::make($this->_data);
            $data->push($value);
            $this->_data = $data->val();
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Remove an element from the set.
     */
    public function remove($value): IVal {
        if ($this->isDsSet($this->_data)) {
            $this->_data->remove($value);
        } else {
            $data = Arr::make($this->_data);
            $data->remove($value);
            $this->_data = $data->val();
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Check if the set contains a specific element.
     */
    public function has($value): bool {
        if ($this->isDsSet($this->_data)) {
            return $this->_data->contains($value);
        }

        return Arr::has($this->_data, $value, true);
    }

    /**
     * Clear all elements in the set.
     */
    public function clear(): IVal {
        if ($this->isDsSet($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Count the elements in the set.
     */
    public function count(): int {
        if ($this->isDsSet($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    /**
     * Get the union of this set with another.
     */
    public function union(mixed $set): mixed {
        if ($this->isDsSet($this->_data) && $this->isDsSet($set)) {
            return $this->_data->union($set);
        }

        return Arr::append(Arr::toArray($this->_data), Arr::toArray($set));
    }

    /**
     * Get the intersection of this set with another.
     */
    public function intersect(mixed $set): mixed {
        if ($this->isDsSet($this->_data) && $this->isDsSet($set)) {
            return $this->_data->intersect($set);
        }

        return Arr::intersect(Arr::toArray($this->_data), Arr::toArray($set));
    }

    /**
     * Get the difference of this set with another.
     */
    public function diff(mixed $set): mixed {
        if ($this->isDsSet($this->_data) && $this->isDsSet($set)) {
            return $this->_data->diff($set);
        }

        return Arr::diff(Arr::toArray($this->_data), Arr::toArray($set));
    }

    protected function supportsDs(): bool
    {
        return class_exists('\Ds\Set');
    }

    protected function isDsSet(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\Set;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $data = Arr::toArray($seed);

        if ($this->supportsDs()) {
            return new \Ds\Set($data);
        }

        return Arr::unique($data);
    }
}

