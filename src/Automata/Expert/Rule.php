<?php
namespace BlueFission\Automata\Expert;

/**
 * Rule
 *
 * Minimal, generic rule encapsulating a predicate over a set
 * of facts and a consequent Fact that is inferred when the
 * predicate holds.
 */
class Rule implements IRule
{
    protected string $_name;
    protected $_predicate;
    protected IFact $_conclusion;

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

    /**
     * Evaluate this rule against a single fact. By default,
     * this delegates to the predicate using the fact's value.
     */
    public function evaluate(IFact $fact): bool
    {
        $fn = $this->_predicate;
        return (bool)$fn($fact->getValue());
    }

    /**
     * Determine if this rule is applicable given the entire
     * fact set. The predicate receives the fact array and
     * must return a boolean.
     *
     * @param array<string,IFact> $facts
     */
    public function isApplicable(array $facts): bool
    {
        return (bool)call_user_func($this->_predicate, $facts);
    }

    /**
     * Return the fact that this rule concludes when fired.
     */
    public function infer(): Fact
    {
        return $this->_conclusion;
    }

    /**
     * Check if a single fact matches this rule. For forward
     * chaining, this is a simple convenience wrapper around
     * evaluate().
     */
    public function matchesFact(IFact $fact): bool
    {
        return $this->evaluate($fact);
    }

    /**
     * Test whether a fact is the consequent of this rule.
     */
    public function hasConsequent(IFact $fact): bool
    {
        return $fact->getName() === $this->_conclusion->getName();
    }

    /**
     * Antecedent is not explicitly modeled; for backward
     * reasoning scenarios, callers can interpret the rule
     * name or other metadata as needed. Here we return a
     * simple Fact placeholder.
     */
    public function getAntecedent(): IFact
    {
        return new Fact($this->_name, true);
    }

    public function getConsequent(): IFact
    {
        return $this->_conclusion;
    }
}
