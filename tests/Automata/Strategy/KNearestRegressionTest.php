<?php

namespace BlueFission\Tests\Automata\Strategy;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Strategy\KNearestRegression;

class KNearestRegressionTest extends TestCase
{
    public function testPredictsApproximateSumOfFeatures(): void
    {
        $reg = new KNearestRegression(3);

        $samples = [
            [1, 2],
            [2, 3],
            [3, 4],
            [4, 5],
        ];

        $labels = [
            3,
            5,
            7,
            9,
        ];

        $reg->train($samples, $labels, 0.0);

        $prediction = $reg->predict([2, 2]);

        $this->assertEqualsWithDelta(4.0, $prediction, 1.0);
    }

    public function testNeighborsReturnClosestPoints(): void
    {
        $reg = new KNearestRegression(2);

        $samples = [
            [0, 0],
            [1, 1],
            [5, 5],
        ];

        $labels = [0, 0, 0];

        $reg->train($samples, $labels, 0.0);

        $neighbors = $reg->neighbors([0.5, 0.5], 2);

        $this->assertCount(2, $neighbors);
        $indices = array_column($neighbors, 'index');

        $this->assertContains(0, $indices);
        $this->assertContains(1, $indices);
    }
}

