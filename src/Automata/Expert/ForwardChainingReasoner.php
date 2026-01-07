<?php
namespace BlueFission\Automata\Expert;

class ForwardChainingReasoner implements IReasoner
{
    protected $_method;

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
            if ($rule->isApplicable($system->getFacts())) {
                $inferredFacts[] = $rule->getConsequent();
            }
        }

        return $inferredFacts;
    }
}
