<?php

namespace BlueFission\Tests\Automata\Anomaly;

use BlueFission\Automata\Anomaly\Strategies\KMeansDetector;
use BlueFission\Automata\Context;
use PHPUnit\Framework\TestCase;

class KMeansDetectorTest extends TestCase
{
    public function testKMeansScoresOutliersHigher(): void
    {
        $detector = new KMeansDetector(2);
        $detector->train([[0, 0], [0.1, 0.1], [10, 10]], [], 0.2);

        $inlierScore = $detector->score([0, 0], new Context());
        $outlierScore = $detector->score([20, 20], new Context());

        $this->assertGreaterThan($inlierScore, $outlierScore);
    }
}
