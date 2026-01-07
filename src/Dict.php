<?php
namespace BlueFission;

use Ds\Map;
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
        parent::__construct(new Map($value), $snapshot, false);
    }

    /**
     * Override the cast method to ensure the data is a Map.
     * @return IVal
     */
    public function cast(): IVal {
        if (!($this->_data instanceof Map)) {
            $this->_data = new Map($this->_data);
        }
        return $this;
    }

    /**
     * Check if value is a Map
     * @return bool
     */
    public function _is(): bool {
        return $this->_data instanceof Map;
    }

    /**
     * Add or update a value in the Map with the specified key
     * @param mixed $key The key to associate the value with
     * @param mixed $value The value to set
     * @return IVal The current instance for chaining
     */
    public function put($key, $value): IVal {
        $this->_data->put($key, $value);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Get the value associated with a specific key
     * @param mixed $key The key to look up
     * @return mixed The value associated with the key, or null if the key doesn't exist
     */
    public function get($key) {
        return $this->_data->get($key, null);
    }

    /**
     * Remove a key-value pair from the Map
     * @param mixed $key The key of the pair to remove
     * @return IVal The current instance for chaining
     */
    public function remove($key): IVal {
        $this->_data->remove($key);
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Clear all key-value pairs in the Map
     * @return IVal The current instance for chaining
     */
    public function clear(): IVal {
        $this->_data->clear();
        $this->trigger(Event::CHANGE);
        return $this;
    }

    /**
     * Check if the Map contains a specific key
     * @param mixed $key The key to check for
     * @return bool True if the key exists, false otherwise
     */
    public function hasKey($key): bool {
        return $this->_data->hasKey($key);
    }

    /**
     * Check if the Map contains a specific value
     * @param mixed $value The value to check for
     * @return bool True if the value exists within the Map, false otherwise
     */
    public function hasValue($value): bool {
        return $this->_data->hasValue($value);
    }

    /**
     * Count the number of key-value pairs in the Map
     * @return int The count of elements
     */
    public function count(): int {
        return $this->_data->count();
    }
}
