<?php
namespace BlueFission\Automata\Expert;

use BlueFission\DevElation as Dev;

class ForwardChainingReasoner implements IReasoner
{
    protected $_method;

    public function __construct(IMethod $method)
    {
        $this->_method = Dev::apply('expert.forward.method', $method);
    }

    public function infer(Expert $system, Fact $fact): array
    {
        $fact = Dev::apply('expert.forward.fact', $fact);
        $rules = $system->getRules();
        $rules = $this->_method->orderRules($rules);
        $rules = Dev::apply('expert.forward.rules_ordered', $rules);

        $inferredFacts = [];
        
        foreach ($rules as $rule) {
            if ($rule->isApplicable($system->getFacts())) {
                $inferredFacts[] = Dev::apply('expert.forward.consequent', $rule->getConsequent());
            }
        }

        $inferredFacts = Dev::apply('expert.forward.inferred', $inferredFacts);
        Dev::do('expert.forward.result', ['infer' => $inferredFacts, 'fact' => $fact]);

        return $inferredFacts;
    }
}
