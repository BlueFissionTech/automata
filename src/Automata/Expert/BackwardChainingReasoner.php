<?php
namespace BlueFission\Automata\Expert;

/**
 * BackwardChainingReasoner
 *
 * Simple backward chaining that, given a goal fact, looks
 * for rules that conclude that fact and returns their
 * antecedents (as modeled by IRule).
 */
class BackwardChainingReasoner implements IReasoner
{
    protected IMethod $_method;

    public function __construct(IMethod $method)
    {
        $this->_method = $method;
    }

    public function infer(Expert $system, Fact $fact): array
    {
        $inferredFacts = [];

        // get rules from system
        $rules = $system->getRules();

        // order rules based on method
        $rules = $this->_method->orderRules($rules);

        foreach ($rules as $rule) {
            if ($rule->hasConsequent($fact)) {
                $inferredFacts[] = $rule->getAntecedent();
            }
        }

        return $inferredFacts;
    }
}
