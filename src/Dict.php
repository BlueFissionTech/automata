<?php
namespace BlueFission;

use BlueFission\Behavioral\Behaviors\Event;

/**
 * Class Dict
 * This class is a value object for Maps.
 * It encapsulates PHP's DS\Map and provides an interface compliant with IVal.
 *
 * @package BlueFission
 * @implements IVal
 */
class Dict extends Val implements IVal {
    protected $_type = DataTypes::GENERIC;

    protected $_forceType = false;

    /**
     * Dict constructor.
     * Initialize the Map with key-value pairs if provided.
     * @param mixed $value Initial key-value pairs for the Map
     * @param bool $snapshot Whether to take a snapshot after initialization
     */
    public function __construct($value = null, bool $snapshot = true) {
        $seed = $value ?? [];
        parent::__construct($this->buildStorage($seed), $snapshot, false);
    }

    /**
     * Override the cast method to ensure the data is a Map.
     * @return IVal
     */
    public function cast(): IVal {
        if ($this->supportsDs()) {
            if (!$this->isDsMap($this->_data)) {
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
     * Check if value is a Map
     * @return bool
     */
    public function _is(): bool {
        return $this->isDsMap($this->_data) || is_array($this->_data);
    }

    /**
     * Add or update a value in the Map with the specified key
     * @param mixed $key The key to associate the value with
     * @param mixed $value The value to set
     * @return IVal The current instance for chaining
     */
    public function put($key, $value): IVal {
        if ($this->isDsMap($this->_data)) {
            $this->_data->put($key, $value);
        } else {
            $this->_data[$key] = $value;
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Get the value associated with a specific key
     * @param mixed $key The key to look up
     * @return mixed The value associated with the key, or null if the key doesn't exist
     */
    public function get($key) {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->get($key, null);
        }

        return $this->_data[$key] ?? null;
    }

    /**
     * Remove a key-value pair from the Map
     * @param mixed $key The key of the pair to remove
     * @return IVal The current instance for chaining
     */
    public function remove($key): IVal {
        if ($this->isDsMap($this->_data)) {
            $this->_data->remove($key);
        } elseif (Arr::hasKey($this->_data, $key)) {
            unset($this->_data[$key]);
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Clear all key-value pairs in the Map
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        if ($this->isDsMap($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Check if the Map contains a specific key
     * @param mixed $key The key to check for
     * @return bool True if the key exists, false otherwise
     */
    public function hasKey($key): bool {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->hasKey($key);
        }

        return Arr::hasKey($this->_data, $key);
    }

    /**
     * Check if the Map contains a specific value
     * @param mixed $value The value to check for
     * @return bool True if the value exists within the Map, false otherwise
     */
    public function hasValue($value): bool {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->hasValue($value);
        }

        return Arr::has($this->_data, $value, true);
    }

    /**
     * Count the number of key-value pairs in the Map
     * @return int The count of elements
     */
    public function count(): int {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    protected function supportsDs(): bool
    {
        return class_exists('\Ds\Map');
    }

    protected function isDsMap(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\Map;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $data = Arr::toArray($seed);

        if ($this->supportsDs()) {
            return new \Ds\Map($data);
        }

        return $data;
    }
}
