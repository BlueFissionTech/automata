<?php

namespace BlueFission\Tests\Automata\Cortex;

use CortexExample\Scenarios\ContinualLearningScenario;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/ContinualLearningScenario.php';

/** Repeated corrections must improve new cases without overwriting the incumbent. */
final class ContinualLearningScenarioTest extends TestCase
{
    public function testTwoObservedBatchesProduceSeparatelyApprovedImprovements(): void
    {
        $report = (new ContinualLearningScenario())->run();
        $this->assertSame('PASS', $report['status'], json_encode($report['checks']));
        $this->assertSame(['maintenance', 'maintenance'], $report['after']);
        $this->assertSame(1.0, $report['second_activation']['evaluation']['candidate']['accuracy']);
    }
}
