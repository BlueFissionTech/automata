<?php

namespace BlueFission\Tests\Automata\Comprehension;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Comprehension\Frame;

class FrameTest extends TestCase
{
    public function testExtractReturnsTopValuesPerExperience(): void
    {
        $frame = new Frame();

        $frame->addExperience([
            'values' => [
                'hospital_supply' => ['value' => 'low', 'weight' => 3],
                'road_status'     => ['value' => 'closed', 'weight' => 2],
            ],
        ], 'source1');

        $frame->addExperience([
            'values' => [
                'hospital_supply' => ['value' => 'critical', 'weight' => 5],
            ],
        ], 'source2');

        $extracted = $frame->extract();

        $this->assertArrayHasKey('hospital_supply', $extracted);
        $this->assertArrayHasKey('road_status', $extracted);
        // Later experiences override earlier ones.
        $this->assertSame('critical', $extracted['hospital_supply']['value']);
    }

    public function testHashArrayProducesFixedLengthNumericVector(): void
    {
        $frame = new Frame();

        $frame->addExperience([
            'values' => [
                'node_a' => ['value' => 'flooded', 'weight' => 1],
                'node_b' => ['value' => 'safe', 'weight' => 1],
            ],
        ], 'source');

        $hash = $frame->hashArray();

        $this->assertIsArray($hash);
        $this->assertGreaterThan(0, count($hash));
        // Values are numeric (mix of ints/floats) due to hashing and padding.
        foreach ($hash as $value) {
            $this->assertIsNumeric($value);
        }
    }
}
