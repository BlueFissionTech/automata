<?php

namespace BlueFission\Tests\Automata\Anomaly;

use BlueFission\Automata\Anomaly\Gateway;
use BlueFission\Automata\Anomaly\Strategies\KNearestDetector;
use BlueFission\Automata\Context;
use PHPUnit\Framework\TestCase;

class GatewayTest extends TestCase
{
    public function testGatewayFlagsAnomalyWithThreshold(): void
    {
        $detector = new KNearestDetector(1, 5.0);
        $detector->train([[0, 0], [1, 1]], [], 0.2);

        $gateway = new Gateway();
        $gateway->registerDetector($detector, 'knn', ['threshold' => 5.0]);

        $result = $gateway->analyze([10, 10], ['context' => new Context()]);
        $anomalies = $result->anomalies();

        $this->assertArrayHasKey('knn', $anomalies);
    }
}
