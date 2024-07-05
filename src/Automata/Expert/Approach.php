<?php
namespace BlueFission\Automata\Expert;

class Approach implements IApproach
{
    protected IReasoner $_reasoner;

    public function __construct(IReasoner $reasoner)
    {
        $this->_reasoner = $reasoner;
    }

    public function execute(Expert $expert): bool
    {
        $facts = $expert->getFacts();
        $rules = $expert->getRules();

        foreach ($facts as $fact) {
            $inferredFacts = $this->_reasoner->infer($expert, $fact);

            foreach ($inferredFacts as $inferredFact) {
                $expert->addFact($inferredFact);
            }
        }

        return true;
    }
}
