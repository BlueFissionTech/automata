<?php
namespace BlueFission\Automata\Expert;

use BlueFission\DevElation as Dev;

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
        $this->_name = Dev::apply('rule.init.name', $name);
        $this->_predicate = Dev::apply('rule.init.predicate', $predicate);
        $this->_conclusion = Dev::apply('rule.init.conclusion', $conclusion);
        Dev::do('rule.created', ['rule' => $this]);
    }

    public function getName(): string
    {
        return Dev::apply('rule.get_name', $this->_name);
    }

    public function getPredicate(): callable
    {
        return Dev::apply('rule.get_predicate', $this->_predicate);
    }

    /**
     * Evaluate this rule against a single fact. By default,
     * this delegates to the predicate using the fact's value.
     */
    public function evaluate(IFact $fact): bool
    {
        $fn = $this->_predicate;
        $result = (bool)$fn($fact->getValue());
        $result = Dev::apply('rule.evaluate', $result);
        Dev::do('rule.evaluated', ['rule' => $this, 'fact' => $fact, 'match' => $result]);
        return $result;
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
        $facts = Dev::apply('rule.applicable.facts', $facts);
        $result = (bool)call_user_func($this->_predicate, $facts);
        $result = Dev::apply('rule.applicable.result', $result);
        Dev::do('rule.applicable', ['rule' => $this, 'facts' => $facts, 'applicable' => $result]);
        return $result;
    }

    /**
     * Return the fact that this rule concludes when fired.
     */
    public function infer(): Fact
    {
        $conclusion = Dev::apply('rule.infer.conclusion', $this->_conclusion);
        Dev::do('rule.infer', ['rule' => $this, 'conclusion' => $conclusion]);
        return $conclusion;
    }

    /**
     * Check if a single fact matches this rule. For forward
     * chaining, this is a simple convenience wrapper around
     * evaluate().
     */
    public function matchesFact(IFact $fact): bool
    {
        $result = $this->evaluate($fact);
        $result = Dev::apply('rule.matches', $result);
        Dev::do('rule.match_result', ['rule' => $this, 'fact' => $fact, 'matches' => $result]);
        return $result;
    }

    /**
     * Test whether a fact is the consequent of this rule.
     */
    public function hasConsequent(IFact $fact): bool
    {
        $result = $fact->getName() === $this->_conclusion->getName();
        $result = Dev::apply('rule.has_consequent', $result);
        Dev::do('rule.consequent_check', ['rule' => $this, 'fact' => $fact, 'consequent' => $result]);
        return $result;
    }

    /**
     * Antecedent is not explicitly modeled; for backward
     * reasoning scenarios, callers can interpret the rule
     * name or other metadata as needed. Here we return a
     * simple Fact placeholder.
     */
    public function getAntecedent(): IFact
    {
        return Dev::apply('rule.get_antecedent', new Fact($this->_name, true));
    }

    public function getConsequent(): IFact
    {
        return Dev::apply('rule.get_conclusion', $this->_conclusion);
    }
}
