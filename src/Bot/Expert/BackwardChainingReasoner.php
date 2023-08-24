<?php
namespace BlueFission\Automata\ExpertSystem;

class BackwardChainingReasoner implements IReasoner
{
    protected $method;

    public function __construct(MethodInterface $method)
    {
        $this->method = $method;
    }

    public function infer(ExpertSystem $system, Fact $fact): array
    {
        $inferredFacts = [];

        // get rules from system
        $rules = $system->getRules();

        // order rules based on method
        $rules = $this->method->orderRules($rules);

        foreach ($rules as $rule) {
            if ($rule->hasConsequent($fact)) {
                $inferredFacts[] = $rule->getAntecedent();
            }
        }

        return $inferredFacts;
    }
}