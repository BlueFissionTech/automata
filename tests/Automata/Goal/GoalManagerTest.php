<?php

namespace BlueFission\Tests\Automata\Goal;

use BlueFission\Automata\Goal\ComparisonOperator;
use BlueFission\Automata\Goal\Condition;
use BlueFission\Automata\Goal\GoalManager;
use BlueFission\Automata\Goal\Initiative;
use PHPUnit\Framework\TestCase;

class GoalManagerTest extends TestCase
{
    public function testGoalManagerTrimsGoalsWithoutStaticArrayCount(): void
    {
        $first = new Initiative(['initiative_id' => 'first', 'name' => 'First']);
        $second = new Initiative(['initiative_id' => 'second', 'name' => 'Second']);

        $manager = new GoalManager([$first, $second], 1);

        $this->assertArrayNotHasKey('first', $manager->goals());
        $this->assertArrayHasKey('second', $manager->goals());
    }

    public function testGoalManagerUpdatesCriteriaAndProvidesFallbackWithoutStaticArrayCount(): void
    {
        $goal = new Initiative(['initiative_id' => 'risk_goal', 'name' => 'Risk Goal']);
        $goal->addCondition(new Condition([
            'path' => 'metrics.risk',
            'operator' => ComparisonOperator::NO_MORE_THAN,
            'value' => 2,
            'type' => 'risk',
        ]));

        $manager = new GoalManager([$goal]);

        $updates = $manager->updateCriteriaSatisfied(['metrics' => ['risk' => 1]]);
        $fallback = (new GoalManager())->recommend([]);

        $fallbackDecision = reset($fallback);

        $this->assertCount(1, $updates);
        $this->assertSame('fallback', $fallbackDecision->toArray()['metadata']['source']);
    }
}
