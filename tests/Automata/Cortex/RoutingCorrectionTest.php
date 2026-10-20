<?php

namespace BlueFission\Tests\Automata\Cortex;

use CortexExample\Scenarios\{GameOracle, RoutingCorrection};
use PHPUnit\Framework\TestCase;

foreach (['ScenarioAdapter', 'GameOracle', 'RoutingCorrection'] as $name) {
    require_once __DIR__ . '/../../../examples/generic/cortex/scenarios/' . $name . '.php';
}

/** Reproduce observed live mistakes without making CI depend on a provider. */
final class RoutingCorrectionTest extends TestCase
{
    /** Prompt-v2 providers got five stones right but still lost at six. */
    public function testPartlyCorrectProviderEvidenceStillImprovesFutureRouting(): void
    {
        $fixtures = json_decode(file_get_contents(__DIR__ . '/../../../examples/generic/cortex/scenarios/observed-game-proposals.json'),
            true, 32, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $fixtures);
        foreach ($fixtures as $fixture) {
            $observations = [];
            foreach ($fixture['observations'] as $row) { $observations[$row['remaining']] = $row['proposal']; }
            $report = (new RoutingCorrection())->run($observations);
            $this->assertSame('PASS', $report['status'], json_encode($report['checks']));
            $this->assertCount(4, $report['holdout']);
            foreach ($report['holdout'] as $row) {
                $this->assertSame('verified.game', $row['selected']['id']);
                $this->assertTrue($row['correct']);
            }
        }
    }

    public function testObservedBadMovesChangeFutureRoutingOnUnseenPositions(): void
    {
        // Sanitized Pokee-Isaac proposals observed on 2026-10-20. The missing
        // completed flag is intentional evidence, not normalized into success.
        $report = (new RoutingCorrection())->run([
            5 => ['action' => 'move', 'take' => 3],
            6 => ['action' => 'move', 'take' => 3, 'completed' => false],
        ]);
        $this->assertSame('PASS', $report['status'], json_encode($report['checks']));
        $this->assertSame([1], GameOracle::winningMoves(5));
        $this->assertSame([2], GameOracle::winningMoves(6));
        $this->assertSame([], GameOracle::winningMoves(8));
        $this->assertCount(4, $report['holdout']);
        $this->assertFalse(RoutingCorrection::winningProposal(
            ['action' => 'move', 'take' => 3, 'completed' => false], 5));
    }
}
