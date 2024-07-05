<?php
namespace BlueFission\Automata\Expert;

class Rule implements IRule
{
    
    protected string $_name;
    protected callable $_predicate;
    // protected $_condition;
    protected $_conclusion;

    public function __construct(string $name, callable $predicate, IFact $conclusion)
    {
        $this->_name = $name;
        $this->_predicate = $predicate;
        $this->_conclusion = $conclusion;
    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function getPredicate(): callable
    {
        return $this->_predicate;
    }

    public function evaluate(IFact $fact): bool
    {
        $fn = $this->_predicate;
        return $fn($fact->getValue());
    }

    public function isApplicable(array $facts): bool
    {
        return call_user_func($this->_predicate, $facts);
    }

    public function infer(): Fact
    {
        return $this->_conclusion;
    }

    public function matchesFact(IFact $fact): bool {
        // Implement your logic to determine if the rule matches the fact
    }

    public function hasConsequent(IFact $fact): bool {
        // Implement your logic to determine if the rule has the fact as a consequent
    }
}
