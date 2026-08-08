<?php

namespace BlueFission\Tests\Automata\Feature;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Feature\ExtendedInteractionFeatures;
use BlueFission\Tests\Automata\Support\RecordingStructureFactory;

class ExtendedInteractionFeaturesTest extends TestCase
{
    public function testGeneratesHigherOrderInteractions(): void
    {
        $features = new ExtendedInteractionFeatures(3);

        $data = [
            [1, 2, 3],
        ];

        $result = $features->transform($data);

        $row = $result->get(0);

        $this->assertNotNull($row);
    }

    public function testUsesInjectedStructureFactory(): void
    {
        $factory = new RecordingStructureFactory();
        $features = new ExtendedInteractionFeatures(3, $factory);

        $result = $features->transform([[1, 2, 3]]);

        $this->assertNotNull($result->get(0));
        $this->assertGreaterThanOrEqual(3, $factory->vecCalls);
    }
}
