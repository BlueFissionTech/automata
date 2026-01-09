<?php

namespace BlueFission\Tests\Automata\Analysis;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Analysis\KNearestExplorer;
use BlueFission\Automata\Analysis\KNearestAnomaly;

class KNearestAnomalyTest extends TestCase
{
    public function testFarPointHasHigherAnomalyScore(): void
    {
        $samples = [
            [0, 0],
            [0.1, 0.1],
            [-0.1, -0.1],
        ];

        $explorer = new KNearestExplorer($samples);
        $anomaly = new KNearestAnomaly($explorer);

        $near = $anomaly->score([0.05, 0.05], 2);
        $far = $anomaly->score([10, 10], 2);

        $this->assertGreaterThan($near, $far);
        $this->assertTrue($anomaly->isAnomalous([10, 10], 2, ($near + $far) / 2));
    }
}

