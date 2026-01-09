<?php
namespace BlueFission\Automata\Expert;

interface IRule
{
    public function __construct(string $name, callable $predicate, IFact $conclusion);
    public function getName(): string;
    public function getPredicate(): callable;
    public function evaluate(IFact $fact): bool;
    public function isApplicable(array $facts): bool;
    public function infer(): Fact;
    public function matchesFact(IFact $fact): bool;
    public function hasConsequent(IFact $fact): bool;
    public function getAntecedent(): IFact;
    public function getConsequent(): IFact;
}
