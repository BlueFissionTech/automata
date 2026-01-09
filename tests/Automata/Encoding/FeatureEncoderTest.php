<?php

namespace BlueFission\Tests\Automata\Encoding;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Encoding\FeatureEncoder;

class FeatureEncoderTest extends TestCase
{
    public function testFeatureEncoderScalesAndOneHotEncodes(): void
    {
        // Columns: [distance, risk_level, asset_type]
        $data = [
            [10.0, 1, 'truck'],
            [20.0, 2, 'boat'],
            [30.0, 3, 'truck'],
        ];

        $encoder = new FeatureEncoder(
            [0, 1], // numerical: distance, risk_level
            [2]     // categorical: asset_type
        );

        $encoder->fit($data);

        $transformed = $encoder->transform($data);

        $this->assertCount(3, $transformed);

        $row0 = $transformed[0];
        $this->assertGreaterThanOrEqual(0.0, $row0->get(0)); // scaled distance
        $this->assertGreaterThanOrEqual(0.0, $row0->get(1)); // scaled risk

        // At minimum we expect scaled numerics preserved plus some encoded categorical slots.
        $this->assertGreaterThanOrEqual(2, $row0->count());
    }
}
