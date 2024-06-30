<?php
namespace BlueFission\Automata\ExpertSystem;

class Rule implements IRule
{
    
    protected string $name;
    protected callable $predicate;
    // protected $condition;
    protected $conclusion;

    public function __construct(string $name, callable $predicate, IFact $conclusion)
    {
        $this->name = $name;
        $this->predicate = $predicate;
        $this->conclusion = $conclusion;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPredicate(): callable
    {
        return $this->predicate;
    }

    public function evaluate(IFact $fact): bool
    {
        $fn = $this->predicate;
        return $fn($fact->getValue());
    }

    public function isApplicable(array $facts): bool
    {
        return call_user_func($this->predicate, $facts);
    }

    public function infer(): Fact
    {
        return $this->conclusion;
    }

    public function matchesFact(IFact $fact): bool {
        // Implement your logic to determine if the rule matches the fact
    }

    public function hasConsequent(IFact $fact): bool {
        // Implement your logic to determine if the rule has the fact as a consequent
    }
}
