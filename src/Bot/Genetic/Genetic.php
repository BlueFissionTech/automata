<?php

namespace BlueFission\Bot\Genetic;

use BlueFission\Behavioral\Configurable;

class Genetic extends Configurable
{
    protected $_mutationRate;

    public function __construct(float $mutationRate = 0.05)
    {
        parent::__construct();
        $this->_mutationRate = $mutationRate;
    }

    public function adapt(array $pressures)
    {
        foreach ($pressures as $key => $pressure) {
            if (isset($this->_data[$key])) {
                $this->_data[$key] = $this->mutateValue($this->_data[$key], $pressure);
            }
        }
    }

    public function clone(array $mutations)
    {
        $clone = new static($this->_mutationRate);
        $clone->_data = $this->_data;
        $clone->_config = $this->_config;

        foreach ($mutations as $key => $mutation) {
            if (isset($clone->_config[$key])) {
                $clone->_config[$key] = $this->mutateValue($clone->_config[$key], $mutation);
            }
        }

        return $clone;
    }

    protected function mutateValue($value, $pressure)
    {
        if (is_numeric($value)) {
            $value += $pressure * $this->_mutationRate;
        } elseif (is_bool($value)) {
            $value = mt_rand() / mt_getrandmax() < $this->_mutationRate ? !$value : $value;
        } elseif (is_string($value)) {
            $value = substr($value, 0, -1) . chr(ord(substr($value, -1)) + intval($pressure * $this->_mutationRate));
        }

        return $value;
    }
}
