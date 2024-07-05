<?php
namespace BlueFission\Automata\Expert;

class BackwardChainingReasoner implements IReasoner
{
    protected $_method;

    public function __construct(MethodInterface $method)
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