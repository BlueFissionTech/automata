<?php

namespace BlueFission\Tests\Automata\Cortex;

use CortexExample\Scenarios\{GameOracle, GameScenario};
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/ScenarioAdapter.php';
require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/GameOracle.php';
require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/GameScenario.php';

/** Outcomes must survive an independent transcript replay, not just routing. */
final class GameScenarioTest extends TestCase
{
    public function testFeedbackImprovesPairedHeldoutGamesWithoutLabelLeakage(): void
    {
        $report = (new GameScenario())->run();
        $this->assertSame('PASS', $report['status'], json_encode($report['checks']));
        $this->assertSame(54, $report['training_episodes']);
        $this->assertSame(27, $report['heldout_pairs']);
        $this->assertGreaterThanOrEqual(0.20, $report['improvement']);
        foreach ($report['pairs'] as $pair) {
            $this->assertSame($pair['baseline']['initial'], $pair['adaptive']['initial']);
            $this->assertSame($pair['baseline']['seed'], $pair['adaptive']['seed']);
            $this->assertTrue(GameOracle::evaluate($pair['adaptive'])['valid']);
            $this->assertSame('take.complement', $pair['adaptive']['moves'][0]['selected']['id']);
        }
    }

    public function testOracleRejectsFabricatedCompletionAndWorldTransitions(): void
    {
        $loss = ['initial' => 2, 'moves' => [
            ['actor' => 'agent', 'before' => 2, 'take' => 1, 'after' => 1],
            ['actor' => 'opponent', 'before' => 1, 'take' => 1, 'after' => 0],
        ], 'winner' => 'opponent'];
        $this->assertSame(['valid' => true, 'won' => false], GameOracle::evaluate($loss));
        $loss['winner'] = 'agent';
        $this->assertFalse(GameOracle::evaluate($loss)['valid']);
        $loss['winner'] = 'opponent';
        $loss['moves'][0]['after'] = 0;
        $this->assertFalse(GameOracle::evaluate($loss)['valid']);
        $loss['moves'] = [];
        $this->assertFalse(GameOracle::evaluate($loss)['valid']);
    }
}
