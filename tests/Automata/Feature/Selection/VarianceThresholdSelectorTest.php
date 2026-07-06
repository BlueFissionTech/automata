<?php

namespace BlueFission\Tests\Automata\Feature\Selection;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Feature\Selection\VarianceThresholdSelector;
use BlueFission\Tests\Automata\Support\RecordingStructureFactory;

class VarianceThresholdSelectorTest extends TestCase
{
    public function testFiltersLowVarianceFeatures(): void
    {
        // Second column is constant (variance 0), first and third vary.
        $data = [
            [1, 5, 10],
            [2, 5, 20],
            [3, 5, 30],
        ];

        $selector = new VarianceThresholdSelector(0.01);
        $filtered = $selector->fitTransform($data);

        $this->assertCount(3, $filtered);
        $this->assertCount(2, $filtered[0]); // one feature dropped
    }

    public function testSelectorUsesInjectedStructureFactory(): void
    {
        $factory = new RecordingStructureFactory();
        $selector = new VarianceThresholdSelector(0.01, $factory);

        $filtered = $selector->fitTransform([
            [1, 1],
            [3, 1],
        ]);

        $this->assertSame([[1], [3]], $filtered);
        $this->assertGreaterThanOrEqual(2, $factory->fillCalls);
        $this->assertGreaterThanOrEqual(2, $factory->arrCalls);
    }

    public function testSelectorHandlesEmptyAndSingleRowData(): void
    {
        $selector = new VarianceThresholdSelector(0.01);

        $this->assertSame([], $selector->fitTransform([]));
        $this->assertSame([[]], $selector->fitTransform([[5, 5]]));
    }
}

