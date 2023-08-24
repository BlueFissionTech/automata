<?php
namespace BlueFission\Automata\ExpertSystem;

class Fact implements IFact
{
    protected string $name;
    protected mixed $value;

    public function __construct(string $name, mixed $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function evaluate(): bool
    {
        // Define the fact evaluation logic here.
        // This could be as simple or as complex as needed.
        // For this example, let's assume the fact value is itself the boolean evaluation.
        return (bool) $this->value;
    }
}
