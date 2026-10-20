<?php

namespace BlueFission\Tests\Automata\Cortex;

use CortexExample\Scenarios\{NpcScenario, OperationsScenario};
use PHPUnit\Framework\TestCase;

foreach (['ScenarioAdapter', 'ScenarioRuntime', 'EffectHost', 'OperationsScenario', 'NpcScenario'] as $name) {
    require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/' . $name . '.php';
}

/** Receiver truth must survive interruption, denial and missing acknowledgements. */
final class WorldScenariosTest extends TestCase
{
    public function testOperationalReplanAndReceiverReconciliation(): void
    {
        $report = (new OperationsScenario())->run();
        $this->assertSame('PASS', $report['status'], json_encode($report['checks']));
        $this->assertTrue(OperationsScenario::validCompletion(
            ['completed' => true, 'proposal' => $report['effects'][0]], $report['effects']));
    }

    public function testNpcInterruptionPreservesInventoryAndCompletesTheNewGoal(): void
    {
        $report = (new NpcScenario())->run();
        $this->assertSame('PASS', $report['status'], json_encode($report['checks']));
        $this->assertSame(3, $report['world']['coins']);
        $this->assertSame(0, $report['world']['potion']);
        $this->assertCount(1, $report['effects']);
    }
}
