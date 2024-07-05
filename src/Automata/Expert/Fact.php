<?php
namespace BlueFission\Automata\Expert;

class Fact implements IFact
{
    protected string $_name;
    protected mixed $_value;

    public function __construct(string $name, mixed $value)
    {
        $this->_name = $name;
        $this->_value = $value;
    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function getValue(): mixed
    {
        return $this->_value;
    }

    public function evaluate(): bool
    {
        // Define the fact evaluation logic here.
        // This could be as simple or as complex as needed.
        // For this example, let's assume the fact value is itself the boolean evaluation.
        return (bool) $this->_value;
    }
}
