<?php

namespace BlueFission\Tests\Automata\Goal;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Goal\Initiative;
use BlueFission\Automata\Goal\Objective;
use BlueFission\Automata\Goal\Condition;

class InitiativeTest extends TestCase
{
    public function testInitiativeTracksChildrenAndCriteria(): void
    {
        $root = new Initiative(['name' => 'Disaster Response']);
        $child = new Initiative(['name' => 'Infrastructure Recovery']);

        $root->addChild($child);
        $root->addObjective(new Objective(['value' => 80]));
        $root->addCondition(new Condition(['value' => 'stable']));

        $this->assertSame($root, $child->parent());
        $this->assertCount(1, $root->children());
        $this->assertCount(2, $root->criteria());
    }
}
