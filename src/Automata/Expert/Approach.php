<?php
namespace BlueFission\Automata\Expert;

use BlueFission\DevElation as Dev;

class Approach implements IApproach
{
    protected IReasoner $_reasoner;

    public function __construct(IReasoner $reasoner)
    {
        $this->_reasoner = Dev::apply('expert.approach.reasoner', $reasoner);
    }

    public function execute(Expert $expert): bool
    {
        $facts = Dev::apply('expert.approach.facts', $expert->getFacts());
        $rules = Dev::apply('expert.approach.rules', $expert->getRules());

        Dev::do('expert.approach.fetched', ['facts' => $facts, 'rules' => $rules]);

        foreach ($facts as $fact) {
            $inferredFacts = $this->_reasoner->infer($expert, $fact);
            $inferredFacts = Dev::apply('expert.approach.inferred', $inferredFacts);

            foreach ($inferredFacts as $inferredFact) {
                $expert->addFact($inferredFact);
            }
        }

        Dev::do('expert.approach.complete', ['expert' => $expert, 'approach' => $this]);

        return Dev::apply('expert.approach.result', true);
    }
}
