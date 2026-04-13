<?php

namespace BlueFission\Tests\Automata\Goal;

use BlueFission\Automata\Goal\Condition;
use PHPUnit\Framework\TestCase;

class ConditionTest extends TestCase
{
    public function testConditionMatchesNestedContextValues(): void
    {
        $condition = new Condition([
            'path' => 'metrics.risk',
            'operator' => 'lte',
            'value' => 2,
            'weight' => 3,
        ]);

        $this->assertTrue($condition->matches([
            'metrics' => ['risk' => 2],
        ]));

        $this->assertSame('metrics.risk', $condition->snapshot()['path']);
        $this->assertSame(2, $condition->snapshot()['expected']);
        $this->assertStringContainsString('metrics.risk', $condition->explain());
    }
}
