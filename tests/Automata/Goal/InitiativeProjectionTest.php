<?php

namespace BlueFission\Tests\Automata\Goal;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Goal\Initiative;
use BlueFission\Automata\Goal\Objective;
use BlueFission\Automata\Goal\Condition;
use BlueFission\Automata\Goal\CriterionType;
use BlueFission\Automata\Goal\ComparisonOperator;
use BlueFission\Automata\Feedback\IProjectionBuilder;
use BlueFission\Automata\Feedback\Projection;

class InitiativeProjectionTest extends TestCase
{
    public function testInitiativeBuildsProjectionsFromCriteria(): void
    {
        $initiative = new Initiative(['name' => 'Disaster Response']);

        $objective = new Objective([
            'type' => CriterionType::TIME,
            'operator' => ComparisonOperator::AT_LEAST,
            'value' => '60',
            'priority' => 0.8,
            'tags' => ['time'],
        ]);

        $condition = new Condition([
            'type' => CriterionType::BEHAVIOR,
            'operator' => ComparisonOperator::IS,
            'value' => 'stabilize',
            'priority' => 0.6,
            'tags' => ['behavior'],
        ]);

        $initiative->addObjective($objective);
        $initiative->addCondition($condition);

        $this->assertInstanceOf(IProjectionBuilder::class, $initiative);

        $projections = $initiative->buildProjections();

        $this->assertCount(2, $projections);
        $this->assertInstanceOf(Projection::class, $projections[0]);

        $tags = $projections[0]->tags();
        $this->assertNotEmpty($tags);
        $this->assertSame('Disaster Response', $projections[0]->context()->get('initiative'));
    }
}
