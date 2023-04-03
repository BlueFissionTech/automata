<?php
namespace BlueFission\Bot;

class DataGroup
{
    private string $_name;
    private array $_strategies;

    public function __construct(string $name)
    {
        $this->_name = $name;
        $this->_strategies = [];
    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function addStrategy(IStrategy $strategy)
    {
        $this->_strategies[] = $strategy;
    }

    public function getStrategies(): array
    {
        return $this->_strategies;
    }
}