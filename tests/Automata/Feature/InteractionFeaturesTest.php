<?php

namespace BlueFission\Tests\Automata\Feature;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Feature\InteractionFeatures;
use BlueFission\Tests\Automata\Support\RecordingStructureFactory;

class InteractionFeaturesTest extends TestCase
{
    public function testAddsPairwiseInteractionTerms(): void
    {
        $features = new InteractionFeatures();

        $data = [
            [1, 2, 3],
        ];

        $result = $features->transform($data);

        $row = $result->get(0);

        // Original features first.
        $this->assertSame(1, $row->get(0));
        $this->assertSame(2, $row->get(1));
        $this->assertSame(3, $row->get(2));

        // Pairwise products: 1*2, 1*3, 2*3.
        $this->assertSame(2, $row->get(3));
        $this->assertSame(3, $row->get(4));
        $this->assertSame(6, $row->get(5));
    }

    public function testUsesInjectedStructureFactory(): void
    {
        $factory = new RecordingStructureFactory();
        $features = new InteractionFeatures($factory);

        $result = $features->transform([[2, 3]]);

        $this->assertSame(6, $result->get(0)->get(2));
        $this->assertGreaterThanOrEqual(3, $factory->vecCalls);
    }
}

