<?php
namespace BlueFission\Automata;

// Context.php
class Context
{
    protected $_data;

    public function __construct()
    {
        $this->_data = [];
    }

    public function set($key, $value): self
    {
        $this->_data[$key] = $value;
        return $this;
    }

    public function get($key, $default = null)
    {
        return $this->_data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->_data;
    }

    public function __sleep()
    {
        // Return the list of properties that should be serialized.
        return array_keys(get_object_vars($this));
    }
}
