<?php
namespace BlueFission\Automata\Expert;

use BlueFission\DevElation as Dev;

class Fact implements IFact
{
    protected string $_name;
    protected mixed $_value;

    public function __construct(string $name, mixed $value)
    {
        $this->_name = Dev::apply('fact.init.name', $name);
        $this->_value = Dev::apply('fact.init.value', $value);
        Dev::do('fact.created', ['fact' => $this]);
    }

    public function getName(): string
    {
        return Dev::apply('fact.name', $this->_name);
    }

    public function getValue(): mixed
    {
        $value = Dev::apply('fact.value', $this->_value);
        Dev::do('fact.access_value', ['fact' => $this, 'value' => $value]);
        return $value;
    }

    public function evaluate(): bool
    {
        $result = (bool) Dev::apply('fact.evaluate', $this->_value);
        Dev::do('fact.evaluated', ['fact' => $this, 'evaluation' => $result]);
        return $result;
    }
}
