<?php

namespace BlueFission\Tests\Automata\Normalization;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Normalization\NumericalScaler;
use BlueFission\Tests\Automata\Support\RecordingStructureFactory;

class NumericalScalerTest extends TestCase
{
    public function testFitTransformProducesZeroMean(): void
    {
        $scaler = new NumericalScaler();

        $data = [10, 20, 30, 40];
        $scaled = $scaler->fitTransform($data);

        $mean = array_sum($scaled) / count($scaled);

        $this->assertEquals(0.0, $mean, '', 1e-10);
    }

    public function testFitTransformHandlesZeroVariance(): void
    {
        $scaler = new NumericalScaler();

        $scaled = $scaler->fitTransform([5, 5, 5]);

        $this->assertSame([0, 0, 0], $scaled);
    }

    public function testFitTransformHandlesEmptyData(): void
    {
        $scaler = new NumericalScaler();

        $this->assertSame([], $scaler->fitTransform([]));
    }

    public function testScalerUsesInjectedStructureFactory(): void
    {
        $factory = new RecordingStructureFactory();
        $scaler = new NumericalScaler($factory);

        $scaled = $scaler->fitTransform([1, 2, 3]);

        $this->assertCount(3, $scaled);
        $this->assertGreaterThanOrEqual(2, $factory->arrCalls);
    }
}

