<?php

namespace BlueFission\Tests\Automata\Feature\Selection;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Feature\Selection\VarianceThresholdSelector;

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
}

