<?php

namespace BlueFission\Tests\Automata\Feature;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Feature\ExtendedInteractionFeatures;

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
}
