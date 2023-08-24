<?php
namespace BlueFission\Automata\ExpertSystem;

interface IFact
{
    public function __construct(string $name, mixed $value);
    public function getName(): string;
    public function getValue(): mixed;
    public function evaluate(): bool;
}