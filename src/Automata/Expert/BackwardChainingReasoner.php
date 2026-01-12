<?php
namespace BlueFission\Automata\Expert;

use BlueFission\DevElation as Dev;

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
        $this->_method = Dev::apply('expert.backward.method', $method);
    }

    public function infer(Expert $system, Fact $fact): array
    {
        $fact = Dev::apply('expert.backward.fact', $fact);
        $rules = $system->getRules();
        $rules = $this->_method->orderRules($rules);
        $rules = Dev::apply('expert.backward.rules_ordered', $rules);

        $inferredFacts = [];

        foreach ($rules as $rule) {
            if ($rule->hasConsequent($fact)) {
                $inferredFacts[] = Dev::apply('expert.backward.antecedent', $rule->getAntecedent());
            }
        }

        $inferredFacts = Dev::apply('expert.backward.inferred', $inferredFacts);
        Dev::do('expert.backward.result', ['infer' => $inferredFacts, 'fact' => $fact]);
        return $inferredFacts;
    }
}
