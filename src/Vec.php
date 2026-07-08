<?php
namespace BlueFission;

use BlueFission\Behavioral\Behaviors\Event;

/**
 * Class Vec
 *
 * Lightweight vector wrapper. Prefers the php-ds Vector type when available,
 * but falls back to a plain PHP array to keep the library usable without
 * additional extensions (for example, in test and CLI environments).
 */
class Vec extends Val implements IVal {
    protected $_type = DataTypes::GENERIC;

    protected $_forceType = false;

    /**
     * Vec constructor.
     *
     * @param mixed $value Initial values for the Vector
     * @param bool  $snapshot Whether to take a snapshot after initialization
     */
    public function __construct($value = null, bool $snapshot = true) {
        $storage = [];

        if (extension_loaded('ds') && class_exists('\Ds\Vector')) {
            $storage = new \Ds\Vector(is_array($value) ? $value : (array)$value);
        } else {
            $storage = is_array($value) ? array_values($value) : (array)$value;
        }

        parent::__construct($storage, $snapshot, false);
    }

    public function cast(): IVal {
        // No-op: we accept either Ds\Vector or array internally.
        return $this;
    }

    public function _is(): bool {
        return is_array($this->_data) || $this->_data instanceof \Ds\Vector;
    }

    public function add($value): IVal {
        if ($this->_data instanceof \Ds\Vector) {
            $this->_data->push($value);
        } else {
            $this->_data[] = $value;
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    public function remove(int $index): IVal {
        if ($this->_data instanceof \Ds\Vector) {
            if ($index >= 0 && $index < $this->_data->count()) {
                $this->_data->remove($index);
                $this->trigger(Event::CHANGE);
            }
        } elseif (isset($this->_data[$index])) {
            array_splice($this->_data, $index, 1);
            $this->trigger(Event::CHANGE);
        }
        return $this;
    }

    public function get(int $index) {
        if ($this->_data instanceof \Ds\Vector) {
            return $this->_data->get($index);
        }
        return $this->_data[$index] ?? null;
    }

    public function set(int $index, $value): IVal {
        if ($this->_data instanceof \Ds\Vector) {
            $this->_data->set($index, $value);
        } else {
            $this->_data[$index] = $value;
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    public function clear(): IVal {
        if ($this->_data instanceof \Ds\Vector) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    public function count(): int {
        if ($this->_data instanceof \Ds\Vector) {
            return $this->_data->count();
        }
        return is_array($this->_data) ? count($this->_data) : 0;
    }

    /**
     * Convenience accessor for underlying values when backed by an array.
     *
     * @return array
     */
    public function val($value = null): mixed {
        if (func_num_args() === 0) {
            if ($this->_data instanceof \Ds\Vector) {
                return $this->_data->toArray();
            }
            return parent::val();
        }

        if ($this->_data instanceof \Ds\Vector && is_array($value)) {
            $this->_data = new \Ds\Vector($value);
            $this->trigger(Event::CHANGE);
            return $this;
        }

        return parent::val($value);
    }
}
