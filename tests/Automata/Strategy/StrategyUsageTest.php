<?php

namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\Routing\StrategyUsage;
use PHPUnit\Framework\TestCase;

final class StrategyUsageTest extends TestCase
{
    public function testExplicitUnknownCostIsNotMeasuredZero(): void
    {
        $unknown = new StrategyUsage(['cost' => null, 'invocations' => 1]);
        $free = new StrategyUsage(['cost' => 0.0, 'invocations' => 1]);

        $this->assertNull($unknown->cost);
        $this->assertNull($unknown->toArray()['cost']);
        $this->assertSame(0.0, $free->cost);
        $this->assertSame(0.0, (new StrategyUsage())->cost);
    }

    public function testUnknownCostPropagatesThroughAccumulationAndInvocationReservation(): void
    {
        $known = new StrategyUsage(['cost' => 0.25]);
        $unknown = new StrategyUsage(['cost' => null]);

        $this->assertNull($known->plus($unknown)->cost);
        $this->assertNull($unknown->plus($known)->cost);
        $this->assertNull($unknown->withMinimumInvocations()->cost);
        $this->assertSame(1, $unknown->withMinimumInvocations()->invocations);
    }

    public function testUnknownCostCannotPassFiniteSpendLimit(): void
    {
        $unknown = new StrategyUsage(['cost' => null]);

        $this->assertFalse($unknown->within(['max_cost' => 0.0]));
        $this->assertFalse($unknown->within(['max_cost' => 1.0]));
        $this->assertTrue($unknown->within([]));
        $this->assertTrue((new StrategyUsage(['cost' => 0.0]))->within(['max_cost' => 0.0]));
    }
}
