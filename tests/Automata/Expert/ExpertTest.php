<?php

namespace BlueFission\Tests\Automata\Expert;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Expert\Expert;
use BlueFission\Automata\Expert\Fact;
use BlueFission\Automata\Expert\Rule;
use BlueFission\Automata\Expert\ForwardChainingReasoner;
use BlueFission\Automata\Expert\BackwardChainingReasoner;
use BlueFission\Automata\Expert\Approach;
use BlueFission\Automata\Expert\DepthFirstMethod;

class ExpertTest extends TestCase
{
    public function testInferAddsNewFactBasedOnRule(): void
    {
        $expert = new Expert();

        $rain = new Fact('rain', true);
        $expert->addFact($rain);

        $wetGround = new Fact('wet_ground', true);

        $rule = new Rule(
            'if_rain_then_wet_ground',
            function ($facts) {
                return isset($facts['rain']) && $facts['rain']->evaluate();
            },
            $wetGround
        );

        $expert->addRule($rule);

        $expert->infer();

        $this->assertTrue($expert->query(new Fact('wet_ground', true)));
    }

    public function testForwardChainingReasonerInfersConsequents(): void
    {
        $expert = new Expert();

        $symptom = new Fact('symptom_fever', true);
        $expert->addFact($symptom);

        $diagnosis = new Fact('diagnosis_infection', true);

        $rule = new Rule(
            'if_fever_then_infection',
            function ($facts) {
                return isset($facts['symptom_fever']) && $facts['symptom_fever']->evaluate();
            },
            $diagnosis
        );

        $expert->addRule($rule);

        $reasoner = new ForwardChainingReasoner(new DepthFirstMethod());
        $approach = new Approach($reasoner);

        $expert->setStrategy($approach);
        $expert->reason();

        $this->assertTrue($expert->query(new Fact('diagnosis_infection', true)));
    }

    public function testBackwardChainingReasonerFindsAntecedents(): void
    {
        $expert = new Expert();

        $goal = new Fact('goal_supply_ready', true);
        $precondition = new Fact('precondition_stocked', true);

        $rule = new Rule(
            'if_stocked_then_supply_ready',
            function ($facts) {
                return isset($facts['precondition_stocked']) && $facts['precondition_stocked']->evaluate();
            },
            $goal
        );

        $expert->addRule($rule);

        $reasoner = new BackwardChainingReasoner(new DepthFirstMethod());

        $antecedents = $reasoner->infer($expert, $goal);

        $this->assertNotEmpty($antecedents);
        $this->assertInstanceOf(Fact::class, $antecedents[0]);
    }
}
