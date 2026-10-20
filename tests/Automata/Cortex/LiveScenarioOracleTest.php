<?php

namespace BlueFission\Tests\Automata\Cortex;

use CortexExample\Scenarios\LiveScenario;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/LiveScenario.php';

/** Oracle sensitivity is testable without making optional provider requests. */
final class LiveScenarioOracleTest extends TestCase
{
    public function testEveryCaseRejectsInventedCompletionAndAuthority(): void
    {
        foreach (LiveScenario::cases() as $case) {
            $expected = $case['expected'];
            $this->assertTrue(LiveScenario::accepted($expected, $expected));
            $this->assertFalse(LiveScenario::accepted([...$expected, 'completed' => true], $expected));
            $this->assertFalse(LiveScenario::accepted([...$expected, 'approved' => true], $expected));
            $this->assertFalse(LiveScenario::accepted([], $expected));
        }
    }
}
