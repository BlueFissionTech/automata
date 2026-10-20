<?php

namespace BlueFission\Tests\Automata\Cortex;

use CortexExample\Scenarios\ConversationScenario;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/ScenarioAdapter.php';
require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/ScenarioRuntime.php';
require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/ConversationScenario.php';

/** A plausible sentence is insufficient: state, timing and receipt gates matter. */
final class ConversationScenarioTest extends TestCase
{
    public function testClarificationAndChangedConstraintsProduceANewConsistentAnswer(): void
    {
        $report = (new ConversationScenario())->run();
        $this->assertSame('PASS', $report['status'], json_encode($report['checks']));
        $this->assertTrue(ConversationScenario::matches($report['answer'],
            ['intent' => 'checkin', 'reference' => 'BOOK-42', 'accessible' => true]));
        $this->assertSame(4, $report['workflow']['dispatches']);
        $this->assertTrue($report['workflow']['settled']);
    }
}
