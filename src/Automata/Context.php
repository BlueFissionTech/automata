<?php
namespace BlueFission\Automata;

class Context
{
    protected $_data;
    protected $_tags;
    protected $_normalized;

    public function __construct(array $data = [], array $tags = [], array $normalized = [])
    {
        $this->_data = $data;
        $this->_tags = $tags;
        $this->_normalized = $normalized;
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

    public function addTag(string $label, float $score = 1.0, array $meta = []): self
    {
        $this->_tags[$label] = [
            'score' => $score,
            'meta' => $meta,
        ];
        return $this;
    }

    public function removeTag(string $label): self
    {
        unset($this->_tags[$label]);
        return $this;
    }

    public function hasTag(string $label): bool
    {
        return array_key_exists($label, $this->_tags);
    }

    public function tag(string $label, $default = null)
    {
        return $this->_tags[$label] ?? $default;
    }

    public function setTags(array $tags): self
    {
        $this->_tags = $tags;
        return $this;
    }

    public function tags(): array
    {
        return $this->_tags;
    }

    public function setNormalization(string $key, $value, array $meta = []): self
    {
        $this->_normalized[$key] = [
            'value' => $value,
            'meta' => $meta,
        ];
        return $this;
    }

    public function normalization(string $key, $default = null)
    {
        return $this->_normalized[$key] ?? $default;
    }

    public function normalizedValue(string $key, $default = null)
    {
        $entry = $this->_normalized[$key] ?? null;
        if (is_array($entry) && array_key_exists('value', $entry)) {
            return $entry['value'];
        }
        return $entry ?? $default;
    }

    public function normalizations(): array
    {
        return $this->_normalized;
    }

    public function __sleep()
    {
        // Return the list of properties that should be serialized.
        return array_keys(get_object_vars($this));
    }
}
